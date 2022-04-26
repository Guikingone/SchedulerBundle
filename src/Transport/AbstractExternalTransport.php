<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractExternalTransport extends AbstractTransport
{
    public function __construct(
        protected ConfigurationInterface $configuration,
        protected ConnectionInterface $connection,
        private SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        parent::__construct($configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface|LazyTaskList
    {
        $storedTasks = new TaskList($this->connection->list()->toArray());

        $list = $this->schedulePolicyOrchestrator->sort($this->getExecutionMode(), $storedTasks);

        return $lazy ? new LazyTaskList($list) : $list;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, bool $lazy = false): TaskInterface|LazyTask
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
