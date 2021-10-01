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
    private ObjectNormalizer $objectNormalizer;

    public function __construct(ObjectNormalizer $objectNormalizer)
    {
        $this->objectNormalizer = $objectNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        return [
            'bag' => NotificationTaskBag::class,
            'body' => $this->objectNormalizer->normalize($object, $format, [
                AbstractNormalizer::CALLBACKS => [
                    'recipients' => static fn (array $innerObject, NotificationTaskBag $outerObject, string $attributeName, string $format = null, array $context = []): array => array_map(static fn (Recipient $recipient): array => [
                        'email' => $recipient->getEmail(),
                        'phone' => $recipient->getPhone(),
                    ], $innerObject),
                    'notification' => static fn (Notification $innerObject, NotificationTaskBag $outerObject, string $attributeName, string $format = null, array $context = []): array => [
                        'subject' => $innerObject->getSubject(),
                        'content' => $innerObject->getContent(),
                        'emoji' => $innerObject->getEmoji(),
                        'channels' => array_merge(...array_map(static fn (Recipient $recipient): array => $innerObject->getChannels($recipient), $outerObject->getRecipients())),
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
        return $this->objectNormalizer->denormalize($data['body'], $type, $format, [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                NotificationTaskBag::class => [
                    'notification' => $this->objectNormalizer->denormalize($data['body']['notification'], Notification::class, $format, $context),
                    'recipients' => array_map(fn (array $recipient): Recipient => $this->objectNormalizer->denormalize($recipient, Recipient::class, $format, $context), $data['body']['recipients']),
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
