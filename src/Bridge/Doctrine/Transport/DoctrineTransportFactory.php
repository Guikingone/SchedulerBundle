<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\TransportFactoryInterface;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function sprintf;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineTransportFactory implements TransportFactoryInterface
{
    private ConnectionRegistry $registry;

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
            'auto_setup' => $dsn->getOptionAsBool('auto_setup', true),
            'connection' => $dsn->getHost(),
            'execution_mode' => $dsn->getOption('execution_mode'),
            'table_name' => $dsn->getOption('tableName', '_symfony_scheduler_tasks'),
        ];

        try {
            $doctrineConnection = $this->registry->getConnection($connectionOptions['connection']);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new TransportException(sprintf('Could not find Doctrine connection from Scheduler DSN "doctrine://%s".', $dsn->getHost()), $invalidArgumentException->getCode(), $invalidArgumentException);
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
