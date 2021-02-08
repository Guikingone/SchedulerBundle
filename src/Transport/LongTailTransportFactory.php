<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailTransportFactory extends AbstractCompoundTransportFactory
{
    /**
     * @var iterable|TransportFactoryInterface[]
     */
    private iterable $transportFactories;

    /**
     * @param iterable|TransportFactoryInterface[] $transportFactories
     */
    public function __construct(iterable $transportFactories)
    {
        $this->transportFactories = $transportFactories;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(
        Dsn $dsn,
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ): LongTailTransport {
        return new LongTailTransport($this->handleTransportDsn(' <> ', $dsn, $this->transportFactories, $configuration, $serializer, $schedulePolicyOrchestrator));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, ConfigurationInterface $configuration): bool
    {
        return 0 === strpos($dsn, 'longtail://') || 0 === strpos($dsn, 'lt://');
    }
}
