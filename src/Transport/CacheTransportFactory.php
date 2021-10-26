<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheTransportFactory implements TransportFactoryInterface
{
    public function __construct(private CacheItemPoolInterface $pool)
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
    ): CacheTransport {
        $configuration->init([
            'execution_mode' => $dsn->getOption('execution_mode', 'first_in_first_out'),
        ]);

        return new CacheTransport($configuration, $this->pool, $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return str_starts_with($dsn, 'cache://');
    }
}
