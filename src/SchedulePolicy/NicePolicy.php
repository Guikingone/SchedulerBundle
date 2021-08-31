<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NicePolicy implements PolicyInterface
{
    /**
     * @var string
     */
    private const POLICY = 'nice';

    public function sort(TaskListInterface $tasks): TaskListInterface
    {
        return $tasks->uasort(function (TaskInterface $task, TaskInterface $nextTask): int {
            if ($task->getPriority() > 0) {
                return 1;
            }

            if ($nextTask->getPriority() > 0) {
                return 1;
            }

            return $task->getNice() <=> $nextTask->getNice();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return self::POLICY === $policy;
    }
}
