<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailTransportFactory extends AbstractCompoundTransportFactory
{
    /**
     * @param iterable|TransportFactoryInterface[] $transportFactories
     */
    public function __construct(private iterable $transportFactories)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        return new LongTailTransport(new TransportRegistry($this->handleTransportDsn(' <> ', $dsn, $this->transportFactories, $options, $serializer, $schedulePolicyOrchestrator)));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return str_starts_with($dsn, 'longtail://') || str_starts_with($dsn, 'lt://');
    }
}
