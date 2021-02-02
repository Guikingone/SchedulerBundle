<?php

declare(strict_types=1);

namespace SchedulerBundle\Serializer;

use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use function array_map;
use function array_merge;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTaskBagNormalizer implements DenormalizerInterface, NormalizerInterface
{
    /**
     * @var ObjectNormalizer
     */
    private $objectNormalizer;

    public function __construct(ObjectNormalizer $objectNormalizer)
    {
        $this->objectNormalizer = $objectNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        return [
            'bag' => NotificationTaskBag::class,
            'body' => $this->objectNormalizer->normalize($object, $format, array_merge($context, $this->handleNormalizationCallbacks())),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof NotificationTaskBag;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $body = $data['body'];

        return $this->objectNormalizer->denormalize($body, $type, $format, [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                NotificationTaskBag::class => [
                    'notification' => $this->objectNormalizer->denormalize($body['notification'], Notification::class, $format, $context),
                    'recipients' => array_map(function (array $recipient) use ($format, $context): Recipient {
                        return $this->objectNormalizer->denormalize($recipient, Recipient::class, $format, $context);
                    }, $body['recipients']),
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return NotificationTaskBag::class === $type;
    }

    private function handleNormalizationCallbacks(): array
    {
        return [
            AbstractNormalizer::CALLBACKS => [
                'recipients' => function (array $innerObject, $outerObject, string $attributeName, string $format = null, array $context = []): array {
                    return array_map(function (Recipient $recipient) use ($format, $context): array {
                        return $this->objectNormalizer->normalize($recipient, $format, $context);
                    }, $innerObject);
                },
                'notification' => function (Notification $innerObject, $outerObject, string $attributeName, string $format = null, array $context = []): array {
                    return [
                        'subject' => $innerObject->getSubject(),
                        'content' => $innerObject->getContent(),
                        'emoji' => $innerObject->getEmoji(),
                        'channel' => $innerObject->getChannels(new Recipient('normalization', '')),
                        'importance' => $innerObject->getImportance(),
                    ];
                },
            ],
        ];
    }
}
