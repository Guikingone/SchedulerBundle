<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TaskExecutionTrackerInterface
{
    /**
     * Allow to track a task, the task must authorize the "tracking" thanks to {@see AbstractTask::setTracked()}.
     */
    public function startTracking(TaskInterface $task): void;

    /**
     * End the tracking of a task, the execution time is available via {@see AbstractTask::getExecutionComputationTime()}.
     */
    public function endTracking(TaskInterface $task): void;
}
