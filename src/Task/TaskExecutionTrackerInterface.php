<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
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
