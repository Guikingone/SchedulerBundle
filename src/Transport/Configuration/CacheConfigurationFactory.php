<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheConfigurationFactory implements ConfigurationFactoryInterface
{
    private CacheItemPoolInterface $pool;

    public function __construct(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): CacheConfiguration
    {
        return new CacheConfiguration($this->pool, [
            'execution_mode' => $dsn->getOption('execution_mode', 'first_in_first_out'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return 0 === strpos($dsn, 'configuration://cache');
    }
}
