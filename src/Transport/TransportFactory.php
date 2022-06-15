<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TransportFactory
{
    /**
     * @param TransportFactoryInterface[] $factories
     */
    public function __construct(private readonly iterable $factories)
    {
    }

    /**
     * @param array<string|int, mixed>            $options
     *
     */
    public function createTransport(
        string $dsn,
        array $options,
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ): TransportInterface {
        foreach ($this->factories as $factory) {
            if ($factory->support($dsn, $options)) {
                return $factory->createTransport(Dsn::fromString($dsn), $options, $configuration, $serializer, $schedulePolicyOrchestrator);
            }
        }

        throw new InvalidArgumentException(sprintf('No transport supports the given Scheduler DSN "%s".', $dsn));
    }
}
