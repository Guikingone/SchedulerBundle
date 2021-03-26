<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
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
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        return new CacheTransport([
            'execution_mode' => $dsn->getOption('execution_mode', 'first_in_first_out'),
        ], $this->pool, $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'cache://');
    }
}
