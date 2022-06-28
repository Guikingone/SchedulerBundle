<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Symfony\Component\Stopwatch\Stopwatch;
use function memory_get_usage;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutionTracker implements TaskExecutionTrackerInterface
{
    public function __construct(private Stopwatch $watch)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function startTracking(TaskInterface $task): void
    {
        if (!$task->isTracked()) {
            return;
        }

        $this->watch->start(name: sprintf('task_execution.%s', $task->getName()));
    }

    /**
     * {@inheritdoc}
     */
    public function endTracking(TaskInterface $task): void
    {
        if (!$task->isTracked()) {
            return;
        }

        $task->setExecutionMemoryUsage(executionMemoryUsage: memory_get_usage());

        if (!$this->watch->isStarted(name: sprintf('task_execution.%s', $task->getName()))) {
            return;
        }

        $stopwatchEvent = $this->watch->stop(name: sprintf('task_execution.%s', $task->getName()));
        $task->setExecutionComputationTime(executionComputationTime: $stopwatchEvent->getDuration());
    }
}
