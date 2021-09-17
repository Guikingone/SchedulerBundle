<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutingEvent extends Event implements TaskEventInterface, WorkerEventInterface
{
    private TaskInterface $task;
    private WorkerInterface $worker;
    private TaskListInterface $currentTasks;

    public function __construct(
        TaskInterface $task,
        WorkerInterface $worker,
        TaskListInterface $currentTasks
    ) {
        $this->task = $task;
        $this->worker = $worker;
        $this->currentTasks = $currentTasks;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    /**
     * {@inheritdoc}
     */
    public function getWorker(): WorkerInterface
    {
        return $this->worker;
    }

    public function getCurrentTasks(): TaskListInterface
    {
        return $this->currentTasks;
    }
}
