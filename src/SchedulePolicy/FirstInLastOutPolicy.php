<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FirstInLastOutPolicy implements PolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function sort(TaskListInterface $tasks): TaskListInterface
    {
        return $tasks->uasort(static fn (TaskInterface $task, TaskInterface $nextTask): int => $task->getScheduledAt() <=> $nextTask->getScheduledAt());
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'first_in_last_out' === $policy;
    }
}
