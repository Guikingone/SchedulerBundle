<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class InMemoryTransport extends AbstractTransport
{
    private $tasks = [];
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
        return $this->list()->get($taskName);
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
        if (\array_key_exists($task->getName(), $this->tasks)) {
            return;
        }

        $this->tasks[$task->getName()] = $task;
        $this->tasks = null !== $this->orchestrator ? $this->orchestrator->sort($this->getExecutionMode(), $this->tasks) : $this->tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $this->list()->offsetSet($taskName, $updatedTask);
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
        $task = $this->list()->get($taskName);
        if (!$task instanceof TaskInterface) {
            throw new InvalidArgumentException(sprintf('The task "%s" does not exist', $taskName));
        }

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
        $task = $this->list()->get($taskName);
        if (!$task instanceof TaskInterface || TaskInterface::ENABLED === $task->getState()) {
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
