<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ExecutionPolicyInterface
{
    public function execute(
        Closure $fetchTaskListFunc,
        Closure $handleTaskFunc
    ): void;

    /**
     * Determine if the current policy supports the given @param string $policy.
     */
    public function support(string $policy): bool;
}
