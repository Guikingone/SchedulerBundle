<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class IdlePolicy implements PolicyInterface
{
    /**
     * @var string
     */
    private const POLICY = 'idle';

    /**
     * @return TaskInterface[]
     */
    public function sort(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): int {
            return $task->getPriority() <= 19 && $task->getPriority() < $nextTask->getPriority() ? 1 : -1;
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
