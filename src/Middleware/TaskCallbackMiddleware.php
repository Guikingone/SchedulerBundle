<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Exception\MiddlewareException;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use function call_user_func;
use function get_class;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskCallbackMiddleware implements PreSchedulingMiddlewareInterface, PostSchedulingMiddlewareInterface, PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface, OrderedMiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $callback = $task->getBeforeScheduling();
        if (null === $callback) {
            return;
        }

        if (false === call_user_func($callback, $task)) {
            throw new MiddlewareException('The task cannot be scheduled as an error occurred on the before scheduling callback');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $callback = $task->getAfterScheduling();
        if (null === $callback) {
            return;
        }

        if (false === call_user_func($callback, $task)) {
            $scheduler->unschedule($task->getName());

            throw new MiddlewareException('The task has encountered an error after scheduling, it has been unscheduled');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preExecute(TaskInterface $task): void
    {
        $callback = $task->getBeforeExecuting();
        if (null === $callback) {
            return;
        }

        if (false === call_user_func($callback, $task)) {
            throw new MiddlewareException(sprintf('The task "%s" has encountered an error when executing the %s::getBeforeExecuting() callback.', $task->getName(), get_class($task), ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
        $callback = $task->getAfterExecuting();
        if (null === $callback) {
            return;
        }

        if (false === call_user_func($callback, $task)) {
            throw new MiddlewareException(sprintf('The task "%s" has encountered an error when executing the %s::getAfterExecuting() callback.', $task->getName(), get_class($task), ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 1;
    }
}
