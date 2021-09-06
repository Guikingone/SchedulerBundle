<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PolicyInterface
{
    /**
     * Sort a @param TaskListInterface $tasks depending on the {@see PolicyInterface::support()} submitted policy.
     *
     * The current sort logic is up to the implementation, if needed, the {@see TaskListInterface::uasort()} method can be used.
     */
    public function sort(TaskListInterface $tasks): TaskListInterface;

    /**
     * Define if the @param string $policy is supported by the current policy.
     */
    public function support(string $policy): bool;
}
