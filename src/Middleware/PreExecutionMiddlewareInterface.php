<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PreExecutionMiddlewareInterface
{
    /**
     * Allow executing logic BEFORE executing the task, the @param TaskInterface $task is the one passed through {@see SchedulerInterface::getDueTasks()}
     * or {@see SchedulerInterface::getTasks()}.
     *
     * @throws Throwable If an error|exception occurs, it must be thrown back.
     */
    public function preExecute(TaskInterface $task): void;
}
