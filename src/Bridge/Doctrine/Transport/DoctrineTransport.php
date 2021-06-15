<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Closure;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\AbstractTransport;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_merge;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
class DoctrineTransport extends AbstractTransport
{
    private Connection $connection;

    public function __construct(
        ConfigurationInterface $configuration,
        DbalConnection $dbalConnection,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator,
        ?LoggerInterface $logger = null
    ) {
        $this->defineOptions(array_merge([
            'auto_setup' => $configuration->get('auto_setup') ?? true,
            'connection' => $configuration->get('connection') ?? null,
            'table_name' => '_symfony_scheduler_tasks',
        ], $configuration->toArray()), [
            'auto_setup' => 'bool',
            'connection' => ['string', 'null'],
            'table_name' => 'string',
        ]);

        $this->connection = new Connection(
            $configuration,
            $dbalConnection,
            $serializer,
            $schedulePolicyOrchestrator,
            $logger
        );

        parent::__construct($configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, bool $lazy = false): TaskInterface
    {
        return $lazy
            ? new LazyTask($name, Closure::bind(fn (): TaskInterface => $this->connection->get($name), $this))
            : $this->connection->get($name)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        $list = $this->connection->list();

        return $lazy ? new LazyTaskList($list) : $list;
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

    public function configureSchema(Schema $schema, DbalConnection $dbalConnection): void
    {
        $this->connection->configureSchema($schema, $dbalConnection);
    }
}
