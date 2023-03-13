<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport;

use Redis;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\TransportFactoryInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function class_exists;
use function phpversion;
use function version_compare;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisTransportFactory implements TransportFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createTransport(
        Dsn $dsn,
        array $options,
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ): RedisTransport {
        if (!class_exists(Redis::class)) {
            throw new LogicException('The Redis extension must be installed.');
        }

        $redisEnabled = phpversion('redis');
        if (false === $redisEnabled) {
            throw new RuntimeException('The Redis extension must be enabled.');
        }

        if (version_compare($redisEnabled, '4.3.0', '<')) {
            throw new LogicException('The redis transport requires php-redis 4.3.0 or higher.');
        }

        $configuration->init([
            'host' => $dsn->getHost(),
            'password' => $dsn->getPassword(),
            'port' => $dsn->getPort() ?? 6379,
            'scheme' => $dsn->getScheme(),
            'timeout' => $dsn->getOption('timeout', 30),
            'auth' => $dsn->getOption('auth'),
            'dbindex' => $dsn->getOption('dbindex'),
            'transaction_mode' => $dsn->getOption('transaction_mode'),
            'execution_mode' => $dsn->getOption('execution_mode', 'first_in_first_out'),
            'list' => $dsn->getOption('list', '_symfony_scheduler_tasks'),
        ], [
            'host' => 'string',
            'password' => ['string', 'null'],
            'port' => 'int',
            'scheme' => 'string',
            'timeout' => 'int',
            'auth' => ['string', 'null'],
            'dbindex' => ['int', 'null'],
            'transaction_mode' => ['string', 'null'],
            'execution_mode' => 'string',
            'list' => 'string',
        ]);

        return new RedisTransport($configuration, $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return str_starts_with($dsn, 'redis://');
    }
}
