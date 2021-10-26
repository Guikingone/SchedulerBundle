<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinTransportFactory extends AbstractCompoundTransportFactory
{
    /**
     * @param TransportFactoryInterface[] $transportFactories
     */
    public function __construct(private iterable $transportFactories)
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
    ): RoundRobinTransport {
        $configuration->init([
            'quantum' => $dsn->getOptionAsInt('quantum', 2),
        ], [
            'quantum' => 'int',
        ]);

        return new RoundRobinTransport(
            new TransportRegistry($this->handleTransportDsn(' && ', $dsn, $this->transportFactories, $options, $serializer, $schedulePolicyOrchestrator),
            $configuration
        );
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return str_starts_with($dsn, 'roundrobin://') || str_starts_with($dsn, 'rr://');
    }
}
