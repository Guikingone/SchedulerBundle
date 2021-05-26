<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use function array_key_exists;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryTransport extends AbstractTransport
{
    /**
     * @var array<string, TaskInterface>
     */
    private array $tasks = [];
    private SchedulePolicyOrchestratorInterface $orchestrator;

    public function __construct(array $options, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator)
    {
        $this->defineOptions($options);
        $this->orchestrator = $schedulePolicyOrchestrator;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): TaskInterface
    {
        if (!array_key_exists($name, $this->tasks)) {
            throw new InvalidArgumentException(sprintf('The task "%s" does not exist', $name));
        }

        return $this->tasks[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        $list = new TaskList($this->orchestrator->sort($this->getExecutionMode(), $this->tasks));

        return $lazy ? new LazyTaskList($list) : $list;
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if (array_key_exists($task->getName(), $this->tasks)) {
            return;
        }

        $this->tasks[$task->getName()] = $task;
        $this->tasks = $this->orchestrator->sort($this->getExecutionMode(), $this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        if (!array_key_exists($name, $this->tasks)) {
            throw new InvalidArgumentException(sprintf('The task "%s" does not exist', $name));
        }

        $this->tasks[$name] = $updatedTask;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        unset($this->tasks[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        $task = $this->get($name);
        if (TaskInterface::PAUSED === $task->getState()) {
            throw new LogicException(sprintf('The task "%s" is already paused', $task->getName()));
        }

        $this->tasks[$name]->setState(TaskInterface::PAUSED);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        $task = $this->get($name);
        if (TaskInterface::ENABLED === $task->getState()) {
            return;
        }

        $this->tasks[$name]->setState(TaskInterface::ENABLED);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->tasks = [];
    }
}
