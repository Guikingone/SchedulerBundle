<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Task\TaskInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskScheduledEvent extends Event implements TaskEventInterface
{
    public function __construct(private readonly TaskInterface $task)
    {
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }
}
