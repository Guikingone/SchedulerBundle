<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheTransportFactory implements TransportFactoryInterface
{
    private CacheItemPoolInterface $pool;

    public function __construct(CacheItemPoolInterface $cacheItemPool)
    {
        $this->pool = $cacheItemPool;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(
        Dsn $dsn,
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ): CacheTransport {
        return new CacheTransport($configuration, $this->pool, $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, ConfigurationInterface $configuration): bool
    {
        return 0 === strpos($dsn, 'cache://');
    }
}
