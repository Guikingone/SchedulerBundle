<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PolicyInterface
{
    /**
     * @param TaskInterface[] $tasks
     *
     * @return TaskInterface[]
     */
    public function sort(array $tasks): array;

    /**
     * Define if the @param string $policy is supported by the current policy.
     */
    public function support(string $policy): bool;
}
