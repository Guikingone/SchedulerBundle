<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
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
    private $tasks = [];

    /**
     * @var SchedulePolicyOrchestratorInterface|null
     */
    private $orchestrator;

    public function __construct(array $options = [], SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator = null)
    {
        $this->defineOptions($options);

        $this->orchestrator = $schedulePolicyOrchestrator;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        if (!array_key_exists($taskName, $this->tasks)) {
            throw new InvalidArgumentException(sprintf('The task "%s" does not exist', $taskName));
        }

        return $this->tasks[$taskName];
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        return new TaskList($this->tasks);
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
        $this->tasks = null !== $this->orchestrator ? $this->orchestrator->sort($this->getExecutionMode(), $this->tasks) : $this->tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        if (!array_key_exists($name, $this->tasks)) {
            $this->create($updatedTask);

            return;
        }

        $this->tasks[$name] = $updatedTask;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        unset($this->tasks[$taskName]);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $task = $this->get($taskName);
        if (TaskInterface::PAUSED === $task->getState()) {
            throw new LogicException(sprintf('The task "%s" is already paused', $task->getName()));
        }

        $task->setState(TaskInterface::PAUSED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $task = $this->get($taskName);
        if (TaskInterface::ENABLED === $task->getState()) {
            return;
        }

        $task->setState(TaskInterface::ENABLED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->tasks = [];
    }
}
