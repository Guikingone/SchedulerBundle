<?php

declare(strict_types=1);

namespace SchedulerBundle\Serializer;

use SchedulerBundle\Exception\BadMethodCallException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Pool\Configuration\SchedulerConfiguration;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

use function array_map;
use function sprintf;

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
        if (!$this->taskNormalizer instanceof NormalizerInterface || !$this->dateTimeZoneNormalizer instanceof NormalizerInterface || !$this->dateTimeNormalizer instanceof NormalizerInterface) {
            throw new BadMethodCallException(sprintf('The "%s()" method cannot be called as injected normalizer does not implements "%s".', __METHOD__, DenormalizerInterface::class));
        }

        $dueTasks = $object->getDueTasks();

        return [
            'timezone' => $this->dateTimeZoneNormalizer->normalize(object: $object->getTimezone(), format: $format, context: $context),
            'synchronizedDate' => $this->dateTimeNormalizer->normalize(object: $object->getSynchronizedDate(), format: $format, context: $context),
            'dueTasks' => $dueTasks->map(func: function (TaskInterface $task) use ($format, $context): array {
                if (!$this->taskNormalizer instanceof TaskNormalizer) {
                    throw new RuntimeException('The task normalizer is not an instance of TaskNormalizer.');
                }

                return $this->taskNormalizer->normalize(object: $task, format: $format, context: $context);
            }, keepKeys: false),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof SchedulerConfiguration;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = []): SchedulerConfiguration
    {
        if (!$this->objectNormalizer instanceof DenormalizerInterface || !$this->taskNormalizer instanceof DenormalizerInterface || !$this->dateTimeZoneNormalizer instanceof DenormalizerInterface || !$this->dateTimeNormalizer instanceof DenormalizerInterface) {
            throw new BadMethodCallException(sprintf('The "%s()" method cannot be called as injected denormalizer does not implements "%s".', __METHOD__, DenormalizerInterface::class));
        }

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
    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
    {
        return SchedulerConfiguration::class === $type;
    }
}
