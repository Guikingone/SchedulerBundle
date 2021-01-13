<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Bridge\Redis\Transport;

use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\TransportFactoryInterface;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class RedisTransportFactory implements TransportFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        if (!class_exists(\Redis::class)) {
            throw new LogicException('The Redis extension must be installed.');
        }

        if (version_compare(phpversion('redis'), '4.3.0', '<')) {
            throw new LogicException('The redis transport requires php-redis 4.3.0 or higher.');
        }

        $connectionOptions = [
            'host' => $dsn->getHost(),
            'password' => $dsn->getPassword(),
            'port' => $dsn->getPort(),
            'scheme' => $dsn->getScheme(),
            'timeout' => $dsn->getOption('timeout'),
            'auth' => $dsn->getOption('host'),
            'dbindex' => $dsn->getOption('dbindex'),
            'transaction_mode' => $dsn->getOption('transaction_mode'),
        ];

        return new RedisTransport(array_merge($connectionOptions, $options), $serializer);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'redis://');
    }
}
