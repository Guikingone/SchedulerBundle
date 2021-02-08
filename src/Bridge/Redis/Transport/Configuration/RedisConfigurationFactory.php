<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport\Configuration;

use Redis;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Transport\Configuration\ConfigurationFactoryInterface;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function class_exists;
use function phpversion;
use function version_compare;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisConfigurationFactory implements ConfigurationFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): RedisConfiguration
    {
        if (!class_exists(Redis::class)) {
            throw new LogicException('The Redis extension must be installed.');
        }

        if (version_compare(phpversion('redis'), '4.3.0', '<')) {
            throw new LogicException('The redis transport requires php-redis 4.3.0 or higher.');
        }

        return new RedisConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return 0 === strpos($dsn, 'configuration://redis');
    }
}
