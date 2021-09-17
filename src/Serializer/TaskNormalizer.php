<?php

declare(strict_types=1);

namespace SchedulerBundle\Serializer;

use Closure;
use DateInterval;
use DatetimeInterface;
use DateTimeZone;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\TaskBag\NotificationTaskBag;
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

    private DateTimeNormalizer $dateTimeNormalizer;
    private DateIntervalNormalizer $dateIntervalNormalizer;
    private ObjectNormalizer $objectNormalizer;
    private DateTimeZoneNormalizer $dateTimeZoneNormalizer;
    private NotificationTaskBagNormalizer $notificationTaskBagNormalizer;
    private AccessLockBagNormalizer $accessLockBagNormalizer;

    public function __construct(
        DateTimeNormalizer            $dateTimeNormalizer,
        DateTimeZoneNormalizer        $dateTimeZoneNormalizer,
        DateIntervalNormalizer        $dateIntervalNormalizer,
        ObjectNormalizer              $objectNormalizer,
        NotificationTaskBagNormalizer $notificationTaskBagNormalizer,
        AccessLockBagNormalizer       $accessLockBagNormalizer
    ) {
        $this->dateTimeNormalizer = $dateTimeNormalizer;
        $this->dateTimeZoneNormalizer = $dateTimeZoneNormalizer;
        $this->dateIntervalNormalizer = $dateIntervalNormalizer;
        $this->objectNormalizer = $objectNormalizer;
        $this->notificationTaskBagNormalizer = $notificationTaskBagNormalizer;
        $this->accessLockBagNormalizer = $accessLockBagNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        if ($object instanceof CallbackTask && $object->getCallback() instanceof Closure) {
            throw new InvalidArgumentException(sprintf('CallbackTask with closure cannot be sent to external transport, consider executing it thanks to "%s::execute()"', Worker::class));
        }

        $dateAttributesCallback = fn (?DatetimeInterface $innerObject, TaskInterface $outerObject, string $attributeName, string $format = null, array $context = []): ?string => $innerObject instanceof DatetimeInterface ? $this->dateTimeNormalizer->normalize($innerObject, $format, [
            DateTimeNormalizer::FORMAT_KEY => "Y-m-d H:i:s.u",
        ]) : null;
        $dateIntervalAttributesCallback = fn (?DateInterval $innerObject, TaskInterface $outerObject, string $attributeName, string $format = null, array $context = []): ?string => $innerObject instanceof DateInterval ? $this->dateIntervalNormalizer->normalize($innerObject, $format, $context) : null;
        $notificationTaskBagCallback = fn (?NotificationTaskBag $innerObject, TaskInterface $outerObject, string $attributeName, string $format = null, array $context = []): ?array => $innerObject instanceof NotificationTaskBag ? $this->notificationTaskBagNormalizer->normalize($innerObject, $format, $context) : null;
        $taskCallbacksAttributesCallback = function ($innerObject, TaskInterface $outerObject, string $attributeName, string $format = null, array $context = []): ?array {
            if ($innerObject instanceof Closure) {
                throw new InvalidArgumentException('The callback cannot be normalized as its a Closure instance');
            }

            return null === $innerObject ? null : [
                'class' => is_object($innerObject[0]) ? $this->objectNormalizer->normalize($innerObject[0], $format, $context) : null,
                'method' => $innerObject[1],
                'type' => get_class($innerObject[0]),
            ];
        };

        $normalizationCallbacks = [
            AbstractNormalizer::CALLBACKS => [
                'arrivalTime' => $dateAttributesCallback,
                'executionAbsoluteDeadline' => $dateIntervalAttributesCallback,
                'executionRelativeDeadline' => $dateIntervalAttributesCallback,
                'executionStartTime' => $dateAttributesCallback,
                'executionEndTime' => $dateAttributesCallback,
                'lastExecution' => $dateAttributesCallback,
                'scheduledAt' => $dateAttributesCallback,
                'timezone' => fn (?DateTimeZone $innerObject, TaskInterface $outerObject, string $attributeName, string $format = null, array $context = []): ?string => $innerObject instanceof DateTimeZone ? $this->dateTimeZoneNormalizer->normalize($innerObject, $format, $context) : null,
                'beforeScheduling' => $taskCallbacksAttributesCallback,
                'afterScheduling' => $taskCallbacksAttributesCallback,
                'beforeExecuting' => $taskCallbacksAttributesCallback,
                'afterExecuting' => $taskCallbacksAttributesCallback,
                'beforeSchedulingNotificationBag' => $notificationTaskBagCallback,
                'afterSchedulingNotificationBag' => $notificationTaskBagCallback,
                'beforeExecutingNotificationBag' => $notificationTaskBagCallback,
                'afterExecutingNotificationBag' => $notificationTaskBagCallback,
                'recipients' => static fn (array $innerObject, NotificationTask $outerObject, string $attributeName, string $format = null, array $context = []): array => array_map(static fn (Recipient $recipient): array => ['email' => $recipient->getEmail(), 'phone' => $recipient->getPhone()], $innerObject),
                'notification' => static fn (Notification $innerObject, NotificationTask $outerObject, string $attributeName, string $format = null, array $context = []): array => [
                    'subject' => $innerObject->getSubject(),
                    'content' => $innerObject->getContent(),
                    'emoji' => $innerObject->getEmoji(),
                    'channels' => array_merge(...array_map(static fn (Recipient $recipient): array => $innerObject->getChannels($recipient), $outerObject->getRecipients())),
                    'importance' => $innerObject->getImportance(),
                ],
                'message' => fn ($innerObject, MessengerTask $outerObject, string $attributeName, string $format = null, array $context = []): array => [
                    'class' => get_class($innerObject),
                    'payload' => $this->objectNormalizer->normalize($innerObject, $format, $context),
                ],
                'callback' => fn ($innerObject, TaskInterface $outerObject, string $attributeName, string $format = null, array $context = []): array => [
                    'class' => is_object($innerObject[0]) ? $this->objectNormalizer->normalize($innerObject[0], $format, $context) : null,
                    'method' => $innerObject[1],
                    'type' => get_class($innerObject[0]),
                ],
                'tasks' => fn (TaskListInterface $innerObject, ChainedTask $outerObject, string $attributeName, string $format = null, array $context = []): array => array_map(fn (TaskInterface $task): array => $this->normalize($task, $format, [
                    AbstractNormalizer::IGNORED_ATTRIBUTES => $task instanceof CommandTask ? [] : [
                        'options' => [],
                    ],
                ]), $innerObject->toArray(false)),
                'accessLockBag' => fn (?AccessLockBag $innerObject, TaskInterface $outerObject, string $attributeName, string $format = null, array $context = []): ?array => $innerObject instanceof AccessLockBag ? $this->accessLockBagNormalizer->normalize($innerObject, $format, $context) : null,
            ],
        ];

        return [
            'body' => $this->objectNormalizer->normalize($object, $format, array_merge($context, $normalizationCallbacks, [
                AbstractNormalizer::IGNORED_ATTRIBUTES => $object instanceof CommandTask ? [] : [
                    'options' => [],
                ],
            ])),
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
    public function denormalize($data, string $type, string $format = null, array $context = []): TaskInterface
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
                        'tasks' => array_map(fn (array $task): TaskInterface => $this->denormalize($task, $type, $format, $context), $body['tasks']),
                    ],
                ],
            ]);
        }

        if (NullTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    NullTask::class => [
                        'name' => $body['name'],
                    ],
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
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    NotificationTask::class => [
                        'name' => $body['name'],
                        'notification' => $this->objectNormalizer->denormalize($body['notification'], Notification::class, $format, $context),
                        'recipients' => array_map(fn (array $recipient): Recipient => $this->objectNormalizer->denormalize($recipient, Recipient::class, $format, $context), $body['recipients']),
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

        if (ProbeTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    ProbeTask::class => [
                        'name' => $body['name'],
                        'externalProbePath' => $body['externalProbePath'],
                        'errorOnFailedTasks' => $body['errorOnFailedTasks'],
                        'delay' => $body['delay'],
                    ],
                ],
            ]);
        }

        throw new InvalidArgumentException('The task cannot be denormalized as the type is not supported');
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return is_array($data) && array_key_exists(self::NORMALIZATION_DISCRIMINATOR, $data);
    }
}
