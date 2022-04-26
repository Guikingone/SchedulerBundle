<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PolicyInterface
{
    /**
     * The current sort logic is up to the implementation, if needed, the {@see TaskListInterface::uasort()} method can be used.
     *
     * @param TaskListInterface<string|int, TaskInterface> $tasks
     *
     * @return TaskListInterface<string|int, TaskInterface>
     */
    public function sort(TaskListInterface $tasks): TaskListInterface;

    /**
     * Define if the @param string $policy is supported by the current policy.
     */
    public function support(string $policy): bool;
}
