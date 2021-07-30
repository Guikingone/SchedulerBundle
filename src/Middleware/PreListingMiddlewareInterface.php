<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PreListingMiddlewareInterface
{
    /**
     * Allow triggering logic BEFORE returning a {@see TaskListInterface}.
     *
     * The @param TaskInterface $task in the one contained in {@see TaskListInterface::walk()}
     *
     * The @param TaskListInterface $taskList can be used to remove tasks not required after the middleware call.
     *
     * @throws Throwable If an error|exception occurs, it must be thrown back.
     */
    public function preListing(TaskInterface $task, TaskListInterface $taskList): void;
}
