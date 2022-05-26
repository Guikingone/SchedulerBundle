<?php

declare(strict_types=1);

namespace SchedulerBundle\Serializer;

use SchedulerBundle\Pool\Configuration\SchedulerConfiguration;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use function array_map;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerConfigurationNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private DenormalizerInterface|NormalizerInterface|TaskNormalizer $taskNormalizer,
        private DenormalizerInterface|NormalizerInterface|DateTimeZoneNormalizer $dateTimeZoneNormalizer,
        private DenormalizerInterface|NormalizerInterface|DateTimeNormalizer $dateTimeNormalizer,
        private DenormalizerInterface|NormalizerInterface|ObjectNormalizer $objectNormalizer
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        $dueTasks = $object->getDueTasks();

        return [
            'timezone' => $this->dateTimeZoneNormalizer->normalize(object: $object->getTimezone(), format: $format, context: $context),
            'synchronizedDate' => $this->dateTimeNormalizer->normalize(object: $object->getSynchronizedDate(), format: $format, context: $context),
            'dueTasks' => $dueTasks->map(func: fn (TaskInterface $task): array => $this->taskNormalizer->normalize(object: $task, format: $format, context: $context), keepKeys: false),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof SchedulerConfiguration;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = []): SchedulerConfiguration
    {
        return $this->objectNormalizer->denormalize(data: $data, type: $type, format: $format, context: [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                SchedulerConfiguration::class => [
                    'timezone' => $this->dateTimeZoneNormalizer->denormalize(data: $data['timezone'], type: $type, format:  $format, context:  $context),
                    'synchronizedDate' => $this->dateTimeNormalizer->denormalize(data: $data['synchronizedDate'], type: $type, format:  $format, context:  $context),
                    'dueTasks' => array_map(callback: fn (array $task): TaskInterface => $this->taskNormalizer->denormalize(data: $task, type: $type, format: $format, context: $context), array: $data['dueTasks']),
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return SchedulerConfiguration::class === $type;
    }
}
