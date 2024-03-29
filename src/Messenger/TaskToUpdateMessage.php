<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToUpdateMessage
{
    public function __construct(private string $taskName, private TaskInterface $task)
    {
    }

    public function getTaskName(): string
    {
        return $this->taskName;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }
}
