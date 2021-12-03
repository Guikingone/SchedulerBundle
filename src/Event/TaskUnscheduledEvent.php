<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUnscheduledEvent extends Event implements TaskEventInterface
{
    public function __construct(private string $task)
    {
    }

    public function getTask(): string
    {
        return $this->task;
    }
}
