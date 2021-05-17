<?php

declare(strict_types=1);

namespace SchedulerBundle\Probe;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ProbeInterface
{
    /**
     * Return the amount of executed tasks, the {@see TaskInterface::getState()} value is not relevant.
     */
    public function getExecutedTasks(): int;

    /**
     * Return the amount of failed tasks during the latest worker execution.
     *
     * @see WorkerInterface::getFailedTasks()
     */
    public function getFailedTasks(): int;

    /**
     * Return the amount of scheduled tasks via {@see SchedulerInterface::getTasks()}
     *
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function getScheduledTasks(): int;
}
