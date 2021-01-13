<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecutionDurationPolicy implements PolicyInterface
{
    private const POLICY = 'execution_duration';

    /**
     * {@inheritdoc}
     */
    public function sort(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            return $task->getExecutionComputationTime() > $nextTask->getExecutionComputationTime();
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
