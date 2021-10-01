<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MemoryUsagePolicy implements PolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function sort(TaskListInterface $tasks): TaskListInterface
    {
        return $tasks->uasort(static fn (TaskInterface $task, TaskInterface $nextTask): int => $task->getExecutionMemoryUsage() <=> $nextTask->getExecutionMemoryUsage());
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'memory_usage' === $policy;
    }
}
