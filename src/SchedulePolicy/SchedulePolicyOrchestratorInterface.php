<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SchedulePolicyOrchestratorInterface
{
    /**
     * Sort tasks using @param string $policy to decide which policy must be applied.
     *
     * @param TaskListInterface<string|int, TaskInterface> $tasks
     *
     * @return TaskListInterface<string|int, TaskInterface>
     */
    public function sort(string $policy, TaskListInterface $tasks): TaskListInterface;
}
