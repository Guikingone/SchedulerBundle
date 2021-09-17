<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class IdlePolicy implements PolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function sort(TaskListInterface $tasks): TaskListInterface
    {
        return $tasks->uasort(static fn (TaskInterface $task, TaskInterface $nextTask): int => $task->getPriority() <= 19 && $task->getPriority() < $nextTask->getPriority() ? 1 : -1);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'idle' === $policy;
    }
}
