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
    private TaskInterface $task;

    public function __construct(TaskInterface $task)
    {
        $this->task = $task;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }
}
