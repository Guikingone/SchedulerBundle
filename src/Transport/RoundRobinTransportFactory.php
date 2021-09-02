<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinTransportFactory extends AbstractCompoundTransportFactory
{
    /**
     * @var TransportFactoryInterface[]
     */
    private iterable $transportFactories;

    /**
     * @param TransportFactoryInterface[] $transportFactories
     */
    public function __construct(iterable $transportFactories)
    {
        $this->transportFactories = $transportFactories;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        return new RoundRobinTransport($this->handleTransportDsn(' && ', $dsn, $this->transportFactories, $options, $serializer, $schedulePolicyOrchestrator), [
            'quantum' => $dsn->getOptionAsInt('quantum', 2),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'roundrobin://') || 0 === strpos($dsn, 'rr://');
    }
}
