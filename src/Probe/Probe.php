<?php

declare(strict_types=1);

namespace SchedulerBundle\Probe;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Probe
{
    /**
     * @var TaskListInterface<string, TaskInterface>
     */
    private TaskListInterface $scheduledTasks;

    /**
     * @var TaskListInterface<string, TaskInterface>
     */
    private TaskListInterface $failedTasks;

    /**
     * @var TaskListInterface<string, TaskInterface>
     */
    private TaskListInterface $executedTasks;

    public function __construct()
    {
        $this->executedTasks = new TaskList();
        $this->failedTasks = new TaskList();
        $this->scheduledTasks = new TaskList();
    }

    public function addScheduledTask(TaskInterface $task): void
    {
        $this->scheduledTasks->add($task);
    }

    public function addFailedTask(TaskInterface $task): void
    {
        $this->failedTasks->add($task);
    }

    public function addExecutedTask(TaskInterface $task): void
    {
        $this->executedTasks->add($task);
    }

    /**
     * @return TaskListInterface<string, TaskInterface>
     */
    public function getScheduledTasks(): TaskListInterface
    {
        return $this->scheduledTasks;
    }

    /**
     * @return TaskListInterface<string, TaskInterface>
     */
    public function getFailedTasks(): TaskListInterface
    {
        return $this->failedTasks;
    }

    /**
     * @return TaskListInterface<string, TaskInterface>
     */
    public function getExecutedTasks(): TaskListInterface
    {
        return $this->executedTasks;
    }
}
