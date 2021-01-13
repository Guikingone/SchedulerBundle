<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\Persistence\ConnectionRegistry;
use SchedulerBundle\Exception\TransportException;
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
final class DoctrineTransportFactory implements TransportFactoryInterface
{
    private $registry;

    public function __construct(ConnectionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        $connectionOptions = [
            'auto_setup' => $dsn->getOption('auto_setup'),
            'connection' => $dsn->getHost(),
            'execution_mode' => $dsn->getOption('execution_mode'),
            'table_name' => $dsn->getOption('tableName'),
        ];

        try {
            $doctrineConnection = $this->registry->getConnection($connectionOptions['connection']);
        } catch (\InvalidArgumentException $exception) {
            throw new TransportException(sprintf('Could not find Doctrine connection from Scheduler DSN "doctrine://%s".', $dsn->getHost()));
        }

        return new DoctrineTransport($connectionOptions, $doctrineConnection, $serializer);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'doctrine://') || 0 === strpos($dsn, 'dbal://');
    }
}
