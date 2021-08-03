<?php

declare(strict_types=1);

namespace SchedulerBundle\Serializer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\TaskBag\ExecutionLockBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Throwable;
use function array_key_exists;
use function array_merge;
use function serialize;
use function unserialize;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LockTaskBagNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private ObjectNormalizer $objectNormalizer;
    private LoggerInterface $logger;

    public function __construct(
        ObjectNormalizer $objectNormalizer,
        ?LoggerInterface $logger = null
    ) {
        $this->objectNormalizer = $objectNormalizer;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        try {
            return [
                'bag' => ExecutionLockBag::class,
                'body' => $this->objectNormalizer->normalize($object, $format, array_merge($context, [
                    AbstractNormalizer::CALLBACKS => [
                        'key' => fn (Key $innerObject, ExecutionLockBag $outerObject, string $attributeName, string $format = null, array $context = []): string => serialize($innerObject),
                    ],
                ])),
            ];
        } catch (Throwable $throwable) {
            $this->logger->warning('The key cannot be serialized as the current lock store does not support it, please consider using a store that support the serialization of the key');

            return [
                'bag' => ExecutionLockBag::class,
                'body' => $this->objectNormalizer->normalize($object, $format, array_merge($context, [
                    AbstractNormalizer::IGNORED_ATTRIBUTES => [
                        'key',
                    ],
                ])),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof ExecutionLockBag;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = []): ExecutionLockBag
    {
        return $this->objectNormalizer->denormalize($data, $type, $format, array_merge($context, [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                ExecutionLockBag::class => [
                    'key' => (array_key_exists('key', $data['body']) && null !== $data['body']['key']) ? unserialize($data['body']['key']) : null,
                ],
            ],
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return ExecutionLockBag::class === $type;
    }
}
