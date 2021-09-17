<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinPolicy implements PolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function sort(TaskListInterface $tasks): TaskListInterface
    {
        return $tasks->uasort(static fn (TaskInterface $task, TaskInterface $nextTask): int => $task->getExecutionComputationTime() >= $task->getMaxDuration() && $task->getExecutionComputationTime() < $nextTask->getExecutionComputationTime() ? 1 : -1);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'round_robin' === $policy;
    }
}
