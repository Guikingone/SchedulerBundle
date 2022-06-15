<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToPauseMessage
{
    public function __construct(private readonly string $task)
    {
    }

    public function getTask(): string
    {
        return $this->task;
    }
}
