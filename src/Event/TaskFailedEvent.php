<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Task\FailedTask;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskFailedEvent extends Event implements TaskEventInterface
{
    private $task;

    public function __construct(FailedTask $task)
    {
        $this->task = $task;
    }

    public function getTask(): FailedTask
    {
        return $this->task;
    }
}
