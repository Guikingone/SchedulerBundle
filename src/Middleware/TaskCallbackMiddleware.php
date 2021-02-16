<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use function call_user_func;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskCallbackMiddleware implements PreSchedulingMiddlewareInterface, PostSchedulingMiddlewareInterface, PostExecutionMiddlewareInterface, OrderedMiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        if (null === $task->getBeforeScheduling()) {
            return;
        }

        if (false === call_user_func($task->getBeforeScheduling(), $task)) {
            throw new RuntimeException('The task cannot be scheduled as en error occurred on the before scheduling callback');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        if (null === $task->getAfterScheduling()) {
            return;
        }

        if (false === call_user_func($task->getAfterScheduling(), $task)) {
            $scheduler->unschedule($task->getName());

            throw new RuntimeException('The task has encounter an error after scheduling, it has been unscheduled');
        }
    }

    public function postExecute(TaskInterface $task): void
    {
        if (null === $task->getAfterExecuting()) {
            return;
        }

        if (false === call_user_func($task->getAfterExecuting(), $task)) {
            throw new RuntimeException(sprintf(
                'The task "%s" has encounter an error when executing the %s::getAfterExecuting() callback',
                $task->getName(),
                get_class($task),
            ));
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
