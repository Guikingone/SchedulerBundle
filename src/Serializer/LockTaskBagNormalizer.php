<?php

declare(strict_types=1);

namespace SchedulerBundle\Serializer;

use SchedulerBundle\TaskBag\LockTaskBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use function serialize;
use function unserialize;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LockTaskBagNormalizer implements NormalizerInterface, DenormalizerInterface
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
            'bag' => LockTaskBag::class,
            'body' => $this->objectNormalizer->normalize($object, $format, array_merge($context, [
                AbstractNormalizer::CALLBACKS => [
                    'key' => fn (Key $innerObject, LockTaskBag $outerObject, string $attributeName, string $format = null, array $context = []): string => serialize($innerObject),
                ],
            ])),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof LockTaskBag;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = []): LockTaskBag
    {
        return $this->objectNormalizer->denormalize($data, $type, $format, array_merge($context, [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                LockTaskBag::class => [
                    'key' => null !== $data['body']['key'] ? unserialize($data['body']['key']) : null,
                ],
            ],
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return LockTaskBag::class === $type;
    }
}
