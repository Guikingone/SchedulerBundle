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
    public function __construct(private DenormalizerInterface|NormalizerInterface|ObjectNormalizer $objectNormalizer)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        return [
            'bag' => NotificationTaskBag::class,
            'body' => $this->objectNormalizer->normalize(object: $object, format: $format, context: [
                AbstractNormalizer::CALLBACKS => [
                    'recipients' => static fn (array $innerObject, NotificationTaskBag $outerObject, string $attributeName, string $format = null, array $context = []): array => array_map(callback: static fn (Recipient $recipient): array => [
                        'email' => $recipient->getEmail(),
                        'phone' => $recipient->getPhone(),
                    ], array: $innerObject),
                    'notification' => static fn (Notification $innerObject, NotificationTaskBag $outerObject, string $attributeName, string $format = null, array $context = []): array => [
                        'subject' => $innerObject->getSubject(),
                        'content' => $innerObject->getContent(),
                        'emoji' => $innerObject->getEmoji(),
                        'channels' => array_merge(...array_map(callback: static fn (Recipient $recipient): array => $innerObject->getChannels($recipient), array: $outerObject->getRecipients())),
                        'importance' => $innerObject->getImportance(),
                    ],
                ],
            ]),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof NotificationTaskBag;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = []): NotificationTaskBag
    {
        return $this->objectNormalizer->denormalize(data: $data['body'], type: $type, format: $format, context: [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                NotificationTaskBag::class => [
                    'notification' => $this->objectNormalizer->denormalize(data: $data['body']['notification'], type: Notification::class, format: $format, context: $context),
                    'recipients' => array_map(callback: fn (array $recipient): Recipient => $this->objectNormalizer->denormalize(data: $recipient, type: Recipient::class, format: $format, context: $context), array: $data['body']['recipients']),
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return NotificationTaskBag::class === $type;
    }
}
