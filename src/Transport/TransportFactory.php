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
     * @var TransportFactoryInterface[]
     */
    private iterable $factories;

    /**
     * @param TransportFactoryInterface[] $transportsFactories
     */
    public function __construct(iterable $transportsFactories)
    {
        $this->factories = $transportsFactories;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(string $dsn, ConfigurationInterface $configuration, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->support($dsn, $configuration)) {
                return $factory->createTransport(Dsn::fromString($dsn), $configuration, $serializer, $schedulePolicyOrchestrator);
            }
        }

        throw new InvalidArgumentException(sprintf('No transport supports the given Scheduler DSN "%s".', $dsn));
    }
}
