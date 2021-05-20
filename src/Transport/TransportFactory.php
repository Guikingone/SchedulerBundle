<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
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
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->support($dsn, $options)) {
                return $factory->createTransport(Dsn::fromString($dsn), $options, $serializer, $schedulePolicyOrchestrator);
            }
        }

        throw new InvalidArgumentException(sprintf('No transport supports the given Scheduler DSN "%s".', $dsn));
    }
}
