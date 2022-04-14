<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DefaultPolicy implements ExecutionPolicyInterface
{
    public function execute(
        Closure $fetchTaskListFunc,
        Closure $handleTaskFunc
    ): void {
        $toExecuteTasks = $fetchTaskListFunc();

        $toExecuteTasks->walk(static function (TaskInterface $task) use ($handleTaskFunc, $toExecuteTasks): void {
            $handleTaskFunc($task, $toExecuteTasks);
        });
    }

    public function support(string $policy): bool
    {
        return 'default' === $policy;
    }
}
