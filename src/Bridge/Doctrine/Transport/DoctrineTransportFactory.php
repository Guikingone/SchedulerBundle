<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\TransportFactoryInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineTransportFactory implements TransportFactoryInterface
{
    public function __construct(private ConnectionRegistry $registry)
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
    ): DoctrineTransport {
        try {
            $doctrineConnection = $this->registry->getConnection($dsn->getHost());
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new TransportException(sprintf('Could not find Doctrine connection from Scheduler DSN "doctrine://%s".', $dsn->getHost()), 0, $invalidArgumentException);
        }

        if (!$doctrineConnection instanceof DoctrineConnection) {
            throw new RuntimeException('The connection is not a valid one');
        }

        $configuration->init([
            'auto_setup' => $dsn->getOptionAsBool('auto_setup', true),
            'execution_mode' => $dsn->getOption('execution_mode', 'first_in_first_out'),
            'table_name' => $dsn->getOption('table_name', '_symfony_scheduler_tasks'),
        ], [
            'auto_setup' => 'bool',
            'execution_mode' => 'string',
            'table_name' => 'string',
        ]);

        return new DoctrineTransport($configuration, $doctrineConnection, $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return str_starts_with($dsn, 'doctrine://') || str_starts_with($dsn, 'dbal://');
    }
}
