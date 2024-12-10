<?php

declare(strict_types=1);

namespace SchedulerBundle\Serializer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Exception\BadMethodCallException;
use SchedulerBundle\TaskBag\AccessLockBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Throwable;

use function is_array;
use function sprintf;

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
    public function normalize($object, ?string $format = null, array $context = []): array
    {
        if (!$this->objectNormalizer instanceof NormalizerInterface) {
            throw new BadMethodCallException(sprintf('The "%s()" method cannot be called as injected normalizer does not implements "%s".', __METHOD__, NormalizerInterface::class));
        }

        try {
            return [
                'bag' => AccessLockBag::class,
                'body' => $this->objectNormalizer->normalize(object: $object, format: $format, context: [
                    AbstractNormalizer::CALLBACKS => [
                        'key' => static fn (Key $innerObject, AccessLockBag $outerObject, string $attributeName, ?string $format = null, array $context = []): string => serialize(value: $innerObject),
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
    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AccessLockBag;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = []): AccessLockBag
    {
        if (!$this->objectNormalizer instanceof DenormalizerInterface) {
            throw new BadMethodCallException(sprintf('The "%s()" method cannot be called as injected denormalizer does not implements "%s".', __METHOD__, DenormalizerInterface::class));
        }

        if (!is_array($data)) {
            throw new BadMethodCallException(sprintf('The "%s()" method cannot be called as the data is not an array.', __METHOD__));
        }

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
    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        return AccessLockBag::class === $type;
    }

    /**
     * @return array<class-string|'*'|'object'|string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return ['*' => true];
    }
}
