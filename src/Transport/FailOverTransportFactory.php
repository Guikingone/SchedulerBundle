<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;

use function str_starts_with;

use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverTransportFactory extends AbstractCompoundTransportFactory
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
    ): FailOverTransport {
        $configuration->init([
            'mode' => $options['mode'] ?? $dsn->getOption('mode', 'normal'),
        ], [
            'mode' => 'string',
        ]);

        return new FailOverTransport(
            new TransportRegistry($this->handleTransportDsn(' || ', $dsn, $this->transportFactories, $options, $configuration, $serializer, $schedulePolicyOrchestrator)),
            $configuration
        );
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return str_starts_with($dsn, 'failover://') || str_starts_with($dsn, 'fo://');
    }
}
