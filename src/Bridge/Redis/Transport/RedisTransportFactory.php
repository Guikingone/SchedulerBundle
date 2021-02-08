<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport;

use Redis;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\TransportFactoryInterface;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function class_exists;
use function phpversion;
use function strpos;
use function version_compare;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisTransportFactory implements TransportFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, ConfigurationInterface $configuration, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        if (!class_exists(Redis::class)) {
            throw new LogicException('The Redis extension must be installed.');
        }

        if (version_compare(phpversion('redis'), '4.3.0', '<')) {
            throw new LogicException('The redis transport requires php-redis 4.3.0 or higher.');
        }

        $configuration->init([
            'host' => $dsn->getHost() ?? '127.0.0.1',
            'password' => $dsn->getPassword(),
            'port' => $dsn->getPort() ?? 6379,
            'scheme' => $dsn->getScheme(),
            'timeout' => $dsn->getOption('timeout', 30),
            'auth' => $dsn->getOption('host'),
            'dbindex' => $dsn->getOption('dbindex', 0),
            'transaction_mode' => $dsn->getOption('transaction_mode'),
            'list' => $dsn->getOption('list', '_symfony_scheduler_tasks'),
        ], [
            'host' => 'string',
            'password' => ['string', 'null'],
            'port' => 'int',
            'scheme' => ['string', 'null'],
            'timeout' => 'int',
            'auth' => ['string', 'null'],
            'dbindex' => 'int',
            'transaction_mode' => ['string', 'null'],
            'list' => 'string',
        ]);

        return new RedisTransport($configuration, $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, ConfigurationInterface $configuration): bool
    {
        return 0 === strpos($dsn, 'redis://');
    }
}
