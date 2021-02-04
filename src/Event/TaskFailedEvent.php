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
    private FailedTask $task;

    public function __construct(FailedTask $task)
    {
        $this->task = $task;
    }

    public function getTask(): FailedTask
    {
        return $this->task;
    }
}
