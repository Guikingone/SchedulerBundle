<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport;

use Redis;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\TransportFactoryInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_merge;
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
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): RedisTransport
    {
        if (!class_exists(Redis::class)) {
            throw new LogicException('The Redis extension must be installed.');
        }

        if (version_compare(phpversion('redis'), '4.3.0', '<')) {
            throw new LogicException('The redis transport requires php-redis 4.3.0 or higher.');
        }

        $connectionOptions = [
            'host' => $dsn->getHost(),
            'password' => $dsn->getPassword(),
            'port' => $dsn->getPort() ?? 6379,
            'scheme' => $dsn->getScheme(),
            'timeout' => $dsn->getOption('timeout', 30),
            'auth' => $dsn->getOption('host'),
            'dbindex' => $dsn->getOption('dbindex'),
            'transaction_mode' => $dsn->getOption('transaction_mode'),
            'execution_mode' => $dsn->getOption('execution_mode', 'first_in_first_out'),
            'list' => $dsn->getOption('list', '_symfony_scheduler_tasks'),
        ];

        return new RedisTransport(array_merge($connectionOptions, $options), $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'redis://');
    }
}
