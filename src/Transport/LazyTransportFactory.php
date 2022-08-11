<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function sprintf;
use function str_starts_with;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTransportFactory implements TransportFactoryInterface
{
    /**
     * @param TransportFactoryInterface[] $factories
     */
    public function __construct(private iterable $factories)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(
        Dsn $dsn,
        array $options,
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ): LazyTransport {
        foreach ($this->factories as $factory) {
            if (!$factory->support($dsn->getOptions()[0])) {
                continue;
            }

            $dsn = Dsn::fromString($dsn->getOptions()[0]);

            return new LazyTransport($factory->createTransport($dsn, $options, $configuration, $serializer, $schedulePolicyOrchestrator));
        }

        throw new RuntimeException(sprintf('No factory found for the DSN "%s"', $dsn->getRoot()));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return str_starts_with($dsn, 'lazy://');
    }
}
