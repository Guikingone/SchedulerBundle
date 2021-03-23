<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Exception\MiddlewareException;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use function call_user_func;
use function get_class;
use function is_null;
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
        if (is_null($task->getBeforeScheduling())) {
            return;
        }

        if (false === call_user_func($task->getBeforeScheduling(), $task)) {
            throw new MiddlewareException('The task cannot be scheduled as an error occurred on the before scheduling callback');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        if (is_null($task->getAfterScheduling())) {
            return;
        }

        if (false === call_user_func($task->getAfterScheduling(), $task)) {
            $scheduler->unschedule($task->getName());

            throw new MiddlewareException('The task has encountered an error after scheduling, it has been unscheduled');
        }
    }

    public function preExecute(TaskInterface $task): void
    {
        if (is_null($task->getBeforeExecuting())) {
            return;
        }

        if (false === call_user_func($task->getBeforeExecuting(), $task)) {
            throw new MiddlewareException(sprintf('The task "%s" has encountered an error when executing the %s::getBeforeExecuting() callback.', $task->getName(), get_class($task), ));
        }
    }

    public function postExecute(TaskInterface $task): void
    {
        if (is_null($task->getAfterExecuting())) {
            return;
        }

        if (false === call_user_func($task->getAfterExecuting(), $task)) {
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
