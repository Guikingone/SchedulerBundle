<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToUpdateMessage
{
    private string $taskName;
    private TaskInterface $task;

    public function __construct(
        string $taskName,
        TaskInterface $task
    ) {
        $this->taskName = $taskName;
        $this->task = $task;
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
