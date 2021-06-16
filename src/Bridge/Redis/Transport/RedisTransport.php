<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport;

use Closure;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\AbstractTransport;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisTransport extends AbstractTransport
{
    private Connection $connection;
    private SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator;

    public function __construct(
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        $this->connection = new Connection($configuration, $serializer);
        $this->schedulePolicyOrchestrator = $schedulePolicyOrchestrator;

        parent::__construct($configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        $list = new TaskList($this->schedulePolicyOrchestrator->sort(
            $this->getConfiguration()->get('execution_mode'),
            $this->connection->list()->toArray()
        ));

        return $lazy ? new LazyTaskList($list) : $list;
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
}
