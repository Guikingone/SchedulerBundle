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
    private const POLICY = 'nice';

    /**
     * {@inheritdoc}
     */
    public function sort(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            if ($task->getPriority() > 0 || $nextTask->getPriority() > 0) {
                return false;
            }

            return $task->getNice() > $nextTask->getNice();
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
