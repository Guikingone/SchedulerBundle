<?php

declare(strict_types=1);

namespace SchedulerBundle\Probe;

use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ProbeInterface
{
    public function getExecutedTasks(): int;

    /**
     * Return the amount of failed tasks during the latest worker execution.
     */
    public function getFailedTasks(): int;

    /**
     * Return the amount of scheduled tasks via {@see SchedulerInterface::getTasks()}
     *
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function getScheduledTasks(): int;
}
