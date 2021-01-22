<?php

declare(strict_types=1);

namespace SchedulerBundle\Serializer;

use Closure;
use DateInterval;
use DatetimeInterface;
use DateTimeZone;
use SchedulerBundle\Task\ChainedTask;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\CallbackTask;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\HttpTask;
use SchedulerBundle\Task\MessengerTask;
use SchedulerBundle\Task\NotificationTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\Worker;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use function array_key_exists;
use function array_map;
use function array_merge;
use function get_class;
use function is_array;
use function is_object;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskNormalizer implements DenormalizerInterface, NormalizerInterface
{
    /**
     * @var string
     */
    private const NORMALIZATION_DISCRIMINATOR = 'taskInternalType';

    /**
     * @var DateTimeNormalizer
     */
    private $dateTimeNormalizer;

    /**
     * @var DateIntervalNormalizer
     */
    private $dateIntervalNormalizer;

    /**
     * @var ObjectNormalizer
     */
    private $objectNormalizer;

    /**
     * @var DateTimeZoneNormalizer
     */
    private $dateTimeZoneNormalizer;

    public function __construct(
        DateTimeNormalizer $dateTimeNormalizer,
        DateTimeZoneNormalizer $dateTimeZoneNormalizer,
        DateIntervalNormalizer $dateIntervalNormalizer,
        ObjectNormalizer $objectNormalizer
    ) {
        $this->dateTimeNormalizer = $dateTimeNormalizer;
        $this->dateTimeZoneNormalizer = $dateTimeZoneNormalizer;
        $this->dateIntervalNormalizer = $dateIntervalNormalizer;
        $this->objectNormalizer = $objectNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        if ($object instanceof CallbackTask && $object->getCallback() instanceof Closure) {
            throw new InvalidArgumentException(sprintf('CallbackTask with closure cannot be sent to external transport, consider executing it thanks to "%s::execute()"', Worker::class));
        }

        $dateAttributesContext = $this->handleDateAttributes();
        $callbacksAttributesContext = $this->handleCallbacksAttributes();

        if ($object instanceof MessengerTask) {
            return [
                'body' => $this->objectNormalizer->normalize($object, $format, array_merge($context, $dateAttributesContext, $callbacksAttributesContext, [
                    AbstractNormalizer::CALLBACKS => [
                        'message' => function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []): array {
                            return [
                                'class' => get_class($innerObject),
                                'payload' => $this->objectNormalizer->normalize($innerObject, $format, $context),
                            ];
                        },
                    ],
                ])),
                self::NORMALIZATION_DISCRIMINATOR => get_class($object),
            ];
        }

        if ($object instanceof CallbackTask) {
            return [
                'body' => $this->objectNormalizer->normalize($object, $format, array_merge($context, $dateAttributesContext, $callbacksAttributesContext, [
                    AbstractNormalizer::CALLBACKS => [
                        'callback' => function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []): array {
                            return [
                                'class' => is_object($innerObject[0]) ? $this->objectNormalizer->normalize($innerObject[0], $format, $context) : null,
                                'method' => $innerObject[1],
                                'type' => get_class($innerObject[0]),
                            ];
                        },
                    ],
                ])),
                self::NORMALIZATION_DISCRIMINATOR => get_class($object),
            ];
        }

        if ($object instanceof ChainedTask) {
            return [
                'body' => $this->objectNormalizer->normalize($object, $format, array_merge($context, $dateAttributesContext, $callbacksAttributesContext, [
                    AbstractNormalizer::CALLBACKS => [
                        'tasks' => function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []) use ($dateAttributesContext, $callbacksAttributesContext): array {
                            return array_map(function (TaskInterface $task) use ($format, $context, $dateAttributesContext, $callbacksAttributesContext): array {
                                return $this->normalize($task, $format, array_merge($context, $dateAttributesContext, $callbacksAttributesContext));
                            }, $innerObject);
                        },
                    ],
                ])),
                self::NORMALIZATION_DISCRIMINATOR => get_class($object),
            ];
        }

        return [
            'body' => $this->objectNormalizer->normalize($object, $format, array_merge($context, $dateAttributesContext, $callbacksAttributesContext)),
            self::NORMALIZATION_DISCRIMINATOR => get_class($object),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof TaskInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $objectType = $data[self::NORMALIZATION_DISCRIMINATOR];
        $body = $data['body'];

        if (CallbackTask::class === $objectType) {
            $callback = [
                $this->objectNormalizer->denormalize($body['callback']['class'], $body['callback']['type']),
                $body['callback']['method'],
            ];

            unset($body['callback']);

            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    CallbackTask::class => [
                        'name' => $body['name'],
                        'callback' => $callback,
                        'arguments' => $body['arguments'],
                    ],
                ],
            ]);
        }

        if (CommandTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    CommandTask::class => [
                        'name' => $body['name'],
                        'command' => $body['command'],
                        'arguments' => $body['arguments'],
                        'options' => $body['options'],
                    ],
                ],
            ]);
        }

        if (ChainedTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    ChainedTask::class => [
                        'name' => $body['name'],
                        'tasks' => array_map(function (array $task) use ($type, $format, $context): TaskInterface {
                            return $this->denormalize($task, $type, $format, $context);
                        }, $body['tasks']),
                    ],
                ],
            ]);
        }

        if (NullTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    NullTask::class => ['name' => $body['name']],
                ],
            ]);
        }

        if (ShellTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    ShellTask::class => [
                        'name' => $body['name'],
                        'command' => $body['command'],
                        'cwd' => $body['cwd'],
                        'environmentVariables' => $body['environmentVariables'],
                        'timeout' => $body['timeout'],
                    ],
                ],
            ]);
        }

        if (MessengerTask::class === $objectType) {
            $message = $this->objectNormalizer->denormalize($body['message']['payload'], $body['message']['class'], $format, $context);

            unset($body['message']);

            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    MessengerTask::class => [
                        'name' => $body['name'],
                        'message' => $message,
                    ],
                ],
            ]);
        }

        if (NotificationTask::class === $objectType) {
            $notification = $this->objectNormalizer->denormalize($body['notification'], Notification::class, $format, $context);
            $recipients = $this->objectNormalizer->denormalize($body['recipients'], Recipient::class, $format, array_merge($context, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    Recipient::class => [
                        'email' => $body['recipients'][0]['email'] ?? '',
                        'phone' => $body['recipients'][0]['phone'] ?? '',
                    ],
                ],
            ]));

            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    NotificationTask::class => [
                        'name' => $body['name'],
                        'notification' => $notification,
                        'recipients' => $recipients,
                    ],
                ],
            ]);
        }

        if (HttpTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    HttpTask::class => [
                        'name' => $body['name'],
                        'url' => $body['url'],
                        'method' => $body['method'],
                        'clientOptions' => $body['clientOptions'],
                    ],
                ],
            ]);
        }

        return $this->objectNormalizer->denormalize($data, $objectType, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return is_array($data) && array_key_exists(self::NORMALIZATION_DISCRIMINATOR, $data);
    }

    /**
     * @return array<string, array<string, Closure>>
     */
    private function handleDateAttributes(): array
    {
        $dateAttributesCallback = function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []): ?string {
            return $innerObject instanceof DatetimeInterface ? $this->dateTimeNormalizer->normalize($innerObject, $format, $context) : null;
        };

        $dateIntervalAttributesCallback = function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []): ?string {
            return $innerObject instanceof DateInterval ? $this->dateIntervalNormalizer->normalize($innerObject, $format, $context) : null;
        };

        return [
            AbstractNormalizer::CALLBACKS => [
                'arrivalTime' => $dateAttributesCallback,
                'executionAbsoluteDeadline' => $dateIntervalAttributesCallback,
                'executionRelativeDeadline' => $dateIntervalAttributesCallback,
                'executionStartTime' => $dateAttributesCallback,
                'executionEndTime' => $dateAttributesCallback,
                'lastExecution' => $dateAttributesCallback,
                'scheduledAt' => $dateAttributesCallback,
                'timezone' => function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []): ?string {
                    return $innerObject instanceof DateTimeZone ? $this->dateTimeZoneNormalizer->normalize($innerObject, $format, $context) : null;
                },
            ],
        ];
    }

    private function handleCallbacksAttributes(): array
    {
        $callback = function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []): ?array {
            if ($innerObject instanceof Closure) {
                throw new InvalidArgumentException('The callback cannot be normalized ask its a Closure instance');
            }

            return null === $innerObject ? null : [
                'class' => is_object($innerObject[0]) ? $this->objectNormalizer->normalize($innerObject[0], $format, $context) : null,
                'method' => $innerObject[1],
                'type' => get_class($innerObject[0]),
            ];
        };

        return [
            AbstractNormalizer::CALLBACKS => [
                'beforeScheduling' => $callback,
                'afterScheduling' => $callback,
                'beforeExecuting' => $callback,
                'afterExecuting' => $callback,
            ],
        ];
    }
}
