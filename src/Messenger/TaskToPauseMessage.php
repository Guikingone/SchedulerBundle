<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToPauseMessage
{
    private string $task;

    public function __construct(string $task)
    {
        $this->task = $task;
    }

    public function getTask(): string
    {
        return $this->task;
    }
}
