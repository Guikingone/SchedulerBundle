<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\Transport\Dsn;

use function str_starts_with;

use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheConfigurationFactory implements ConfigurationFactoryInterface
{
    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): CacheConfiguration
    {
        return new CacheConfiguration($this->pool);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return str_starts_with($dsn, 'configuration://cache');
    }
}
