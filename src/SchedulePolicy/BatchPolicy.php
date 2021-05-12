<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\AbstractTask;
use SchedulerBundle\Task\TaskInterface;
use function array_walk;
use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BatchPolicy implements PolicyInterface
{
    /**
     * @return TaskInterface[]
     */
    public function sort(array $tasks): array
    {
        array_walk($tasks, function (TaskInterface $task): void {
            $priority = $task->getPriority();
            if ($priority <= AbstractTask::MIN_PRIORITY) {
                return;
            }
            if ($priority >= AbstractTask::MAX_PRIORITY) {
                return;
            }
            $task->setPriority(--$priority);
        });

        uasort($tasks, fn (TaskInterface $task, TaskInterface $nextTask): int => $task->getPriority() <=> $nextTask->getPriority());

        return $tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'batch' === $policy;
    }
}
