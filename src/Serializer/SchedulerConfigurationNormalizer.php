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
    private TaskNormalizer $taskNormalizer;
    private DateTimeZoneNormalizer $dateTimeZoneNormalizer;
    private DateTimeNormalizer $dateTimeNormalizer;
    private ObjectNormalizer $objectNormalizer;

    public function __construct(
        TaskNormalizer $taskNormalizer,
        DateTimeZoneNormalizer $dateTimeZoneNormalizer,
        DateTimeNormalizer $dateTimeNormalizer,
        ObjectNormalizer $objectNormalizer
    ) {
        $this->taskNormalizer = $taskNormalizer;
        $this->dateTimeZoneNormalizer = $dateTimeZoneNormalizer;
        $this->dateTimeNormalizer = $dateTimeNormalizer;
        $this->objectNormalizer = $objectNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        $dueTasks = $object->getDueTasks();

        return [
            'timezone' => $this->dateTimeZoneNormalizer->normalize($object->getTimezone(), $format, $context),
            'synchronizedDate' => $this->dateTimeNormalizer->normalize($object->getSynchronizedDate(), $format, $context),
            'dueTasks' => $dueTasks->map(fn (TaskInterface $task): array => $this->taskNormalizer->normalize($task, $format, $context), false),
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
        return $this->objectNormalizer->denormalize($data, $type, $format, [
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                SchedulerConfiguration::class => [
                    'timezone' => $this->dateTimeZoneNormalizer->denormalize($data['timezone'], $type, $format, $context),
                    'synchronizedDate' => $this->dateTimeNormalizer->denormalize($data['synchronizedDate'], $type, $format, $context),
                    'dueTasks' => array_map(fn (array $task): TaskInterface => $this->taskNormalizer->denormalize($task, $type, $format, $context), $data['dueTasks']),
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
