<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SchedulePolicyOrchestratorInterface
{
    /**
     * Sort tasks using @param string $policy to decide which policy must be applied.
     *
     * The given @param TaskListInterface $tasks is returned once the sort policy has been applied.
     */
    public function sort(string $policy, TaskListInterface $tasks): TaskListInterface;
}
