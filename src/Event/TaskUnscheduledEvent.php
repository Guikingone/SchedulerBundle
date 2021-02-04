<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUnscheduledEvent extends Event implements TaskEventInterface
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
