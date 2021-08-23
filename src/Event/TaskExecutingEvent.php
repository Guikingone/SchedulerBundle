<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutingEvent extends Event implements TaskEventInterface, WorkerEventInterface
{
    private TaskInterface $task;
    private WorkerInterface $worker;

    public function __construct(
        TaskInterface $task,
        WorkerInterface $worker
    ) {
        $this->task = $task;
        $this->worker = $worker;
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
}
