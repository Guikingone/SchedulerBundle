<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\AbstractTransport;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
class DoctrineTransport extends AbstractTransport
{
    private Connection $connection;

    /**
     * @param array<string, bool|int|string|null> $options
     */
    public function __construct(
        array $options,
        DbalConnection $driverConnection,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator,
        ?LoggerInterface $logger = null
    ) {
        $this->defineOptions([
            'auto_setup' => $options['auto_setup'] ?? true,
            'connection' => $options['connection'] ?? null,
            'table_name' => '_symfony_scheduler_tasks',
        ], $options), [
            'auto_setup' => 'bool',
            'connection' => ['string', 'null'],
            'table_name' => 'string',
        ]);

        $this->connection = new Connection(
            $this->getOptions(),
            $driverConnection,
            $serializer,
            $schedulePolicyOrchestrator,
            $logger
        );
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): TaskInterface
    {
        return $this->connection->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        return $this->connection->list();
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $this->connection->create($task);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        $this->connection->update($name, $updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        $this->connection->pause($name);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        $this->connection->resume($name);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        $this->connection->delete($name);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->connection->empty();
    }

    public function configureSchema(Schema $schema, DbalConnection $connection): void
    {
        $this->connection->configureSchema($schema, $connection);
    }
}
