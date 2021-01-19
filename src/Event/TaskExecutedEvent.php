<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskExecutedEvent extends Event implements TaskEventInterface
{
    private $task;
    private $output;

    public function __construct(TaskInterface $task, Output $output = null)
    {
        $this->task = $task;
        $this->output = $output;
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
