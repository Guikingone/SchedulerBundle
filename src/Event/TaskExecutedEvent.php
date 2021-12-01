<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutedEvent extends Event implements TaskEventInterface
{
    public function __construct(private TaskInterface $task, private Output $output)
    {
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getOutput(): Output
    {
        return $this->output;
    }
}
