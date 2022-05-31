<?php

declare(strict_types=1);

namespace SchedulerBundle\Serializer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\TaskBag\AccessLockBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AccessLockBagNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private ObjectNormalizer|NormalizerInterface|DenormalizerInterface $objectNormalizer,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        try {
            return [
                'bag' => AccessLockBag::class,
                'body' => $this->objectNormalizer->normalize(object: $object, format: $format, context: [
                    AbstractNormalizer::CALLBACKS => [
                        'key' => static fn (Key $innerObject, AccessLockBag $outerObject, string $attributeName, string $format = null, array $context = []): string => serialize(value: $innerObject),
                    ],
                ]),
            ];
        } catch (Throwable) {
            $this->logger->warning(message: 'The key cannot be serialized as the current lock store does not support it, please consider using a store that support the serialization of the key');

            return [
                'bag' => AccessLockBag::class,
                'body' => $this->objectNormalizer->normalize(object: $object, format: $format, context: [
                    AbstractNormalizer::IGNORED_ATTRIBUTES => [
                        'key',
                    ],
                ]),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof AccessLockBag;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = []): AccessLockBag
    {
        return $this->objectNormalizer->denormalize(data: $data, type: $type, format: $format, context: [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                AccessLockBag::class => [
                    'key' => (array_key_exists(key: 'key', array: $data['body']) && null !== $data['body']['key']) ? unserialize(data: $data['body']['key']) : null,
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return AccessLockBag::class === $type;
    }
}
