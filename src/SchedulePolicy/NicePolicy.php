<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NicePolicy implements PolicyInterface
{
    /**
     * @var string
     */
    private const POLICY = 'nice';

    /**
     * @return TaskInterface[]
     */
    public function sort(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): int {
            if ($task->getPriority() > 0) {
                return 1;
            }
            if ($nextTask->getPriority() > 0) {
                return 1;
            }
            return $task->getNice() > $nextTask->getNice() ? 1 : -1;
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
