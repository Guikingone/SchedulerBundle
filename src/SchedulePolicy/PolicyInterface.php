<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PolicyInterface
{
    public function sort(TaskListInterface $tasks): TaskListInterface;

    /**
     * Define if the @param string $policy is supported by the current policy.
     */
    public function support(string $policy): bool;
}
