<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use function uasort;

/**
 * @author Jérémy Vancoillie <contact@jeremyvancoillie.fr>
 */
final class PriorityPolicy implements PolicyInterface
{
    /**
     * @return TaskInterface[]
     */
    public function sort(array $tasks): array
    {
        uasort($tasks, fn (TaskInterface $task, TaskInterface $nextTask): int => $task->getPriority() <=> $nextTask->getPriority());

        return $tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'priority' === $policy;
    }
}
