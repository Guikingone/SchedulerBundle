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
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryTransport extends AbstractTransport
{
    /**
     * @var TaskListInterface<string|int, TaskInterface>
     */
    private TaskListInterface $tasks;

    public function __construct(
        protected ConfigurationInterface $configuration,
        private SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        $this->tasks = new TaskList();

        parent::__construct(configuration: $configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, bool $lazy = false): TaskInterface|LazyTask
    {
        if ($lazy) {
            return new LazyTask(name: $name, sourceTaskClosure: Closure::bind(closure: fn (): TaskInterface => $this->get(name: $name), newThis: $this));
        }

        return $this->tasks->get(taskName: $name);
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface|LazyTaskList
    {
        $list = $this->schedulePolicyOrchestrator->sort(policy: $this->getExecutionMode(), tasks: $this->tasks);

        return $lazy ? new LazyTaskList(sourceList: $list) : $list;
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if ($this->tasks->has(taskName: $task->getName())) {
            return;
        }

        $this->tasks->add(task: $task);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        if (!$this->tasks->has(taskName: $name)) {
            throw new InvalidArgumentException(message: sprintf('The task "%s" does not exist', $name));
        }

        $this->tasks->add(task: $updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        $this->tasks->remove(taskName: $name);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        $task = $this->get(name: $name);
        if (TaskInterface::PAUSED === $task->getState()) {
            throw new LogicException(message: sprintf('The task "%s" is already paused', $task->getName()));
        }

        $this->tasks->add(task: $task->setState(state: TaskInterface::PAUSED));
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        $task = $this->get(name: $name);
        if (TaskInterface::ENABLED === $task->getState()) {
            return;
        }

        $this->tasks->add(task: $task->setState(state: TaskInterface::ENABLED));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->tasks = new TaskList();
    }
}
