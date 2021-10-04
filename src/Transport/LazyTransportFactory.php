<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function sprintf;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTransportFactory implements TransportFactoryInterface
{
    /**
     * @var TransportFactoryInterface[] $factories
     */
    private iterable $factories;

    /**
     * @param TransportFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, ConfigurationInterface $configuration, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): LazyTransport
    {
        foreach ($this->factories as $factory) {
            if (!$factory->support($dsn->getRoot(), $configuration)) {
                continue;
            }

            return new LazyTransport($factory->createTransport($dsn, $configuration, $serializer, $schedulePolicyOrchestrator));
        }

        throw new RuntimeException(sprintf('No factory found for the DSN "%s"', $dsn->getRoot()));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, ConfigurationInterface $configuration): bool
    {
        return 0 === strpos($dsn, 'lazy://');
    }
}
