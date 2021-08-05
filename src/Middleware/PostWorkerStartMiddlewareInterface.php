<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PostWorkerStartMiddlewareInterface
{
    /**
     * Allow triggering logic AFTER the worker has started.
     *
     * The @param TaskListInterface $taskList can be used to remove tasks not required after the middleware call.
     *
     * @throws Throwable If an error|exception occurs, it must be thrown back.
     */
    public function postWorkerStart(TaskListInterface $taskList, WorkerInterface $worker): void;
}
