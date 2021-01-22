<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FirstInFirstOutPolicy implements PolicyInterface
{
    /**
     * @var string
     */
    private const POLICY = 'first_in_first_out';

    /**
     * @return TaskInterface[]
     */
    public function sort(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): int {
            return $task->getScheduledAt() < $nextTask->getScheduledAt() ? 1 : -1;
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
