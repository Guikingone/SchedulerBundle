<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToExecuteMessage
{
    public function __construct(private TaskInterface $task, private int $workerTimeout = 1)
    {
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getWorkerTimeout(): int
    {
        return $this->workerTimeout;
    }
}
