<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Task\FailedTask;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskFailedEvent extends Event implements TaskEventInterface
{
    public function __construct(private readonly FailedTask $task)
    {
    }

    public function getTask(): FailedTask
    {
        return $this->task;
    }
}
