<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BatchPolicy implements PolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function sort(TaskListInterface $tasks): TaskListInterface
    {
        $tasks->walk(static function (TaskInterface $task): void {
            $priority = $task->getPriority();
            $task->setPriority(--$priority);
        });

        return $tasks->uasort(static fn (TaskInterface $task, TaskInterface $nextTask): int => $task->getPriority() <=> $nextTask->getPriority());
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'batch' === $policy;
    }
}
