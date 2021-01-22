<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MemoryUsagePolicy implements PolicyInterface
{
    /**
     * @var string
     */
    private const POLICY = 'memory_usage';

    /**
     * @return TaskInterface[]
     */
    public function sort(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): int {
            return $task->getExecutionMemoryUsage() > $nextTask->getExecutionMemoryUsage() ? 1 : -1;
        });

        return $tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return self::POLICY === $policy;
    }
}
