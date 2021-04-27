<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FirstInLastOutPolicy implements PolicyInterface
{
    /**
     * @return TaskInterface[]
     */
    public function sort(array $tasks): array
    {
        uasort($tasks, fn (TaskInterface $task, TaskInterface $nextTask): int => $task->getScheduledAt() <=> $nextTask->getScheduledAt());

        return $tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'first_in_last_out' === $policy;
    }
}
