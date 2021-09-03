<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryTransport extends AbstractTransport
{
    private TaskListInterface $tasks;
    private SchedulePolicyOrchestratorInterface $orchestrator;

    public function __construct(
        array $options,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        $this->defineOptions($options);
        $this->orchestrator = $schedulePolicyOrchestrator;
        $this->tasks = new TaskList();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, bool $lazy = false): TaskInterface
    {
        if ($lazy) {
            return new LazyTask($name, Closure::bind(fn (): TaskInterface => $this->get($name), $this));
        }

        return $this->tasks->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        $list = $this->orchestrator->sort($this->getExecutionMode(), $this->tasks);

        return $lazy ? new LazyTaskList($list) : $list;
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if ($this->tasks->has($task->getName())) {
            return;
        }

        $this->tasks->add($task);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        if (!$this->tasks->has($name)) {
            throw new InvalidArgumentException(sprintf('The task "%s" does not exist', $name));
        }

        $this->tasks->add($updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        $this->tasks->remove($name);
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

        $this->tasks->add($task->setState(TaskInterface::PAUSED));
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

        $this->tasks->add($task->setState(TaskInterface::ENABLED));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->tasks = new TaskList();
    }
}
