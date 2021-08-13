<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PostExecutionMiddlewareInterface
{
    /**
     * Allow executing logic AFTER executing the task,
     * the @param TaskInterface $task is the one returned via {@see SchedulerInterface::getDueTasks()} or
     * {@see SchedulerInterface::getTasks()}.
     *
     * @throws Throwable If an error|exception occurs, it must be thrown back.
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void;
}
