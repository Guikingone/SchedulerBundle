<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DefaultPolicy implements ExecutionPolicyInterface
{
    public function execute(
        TaskListInterface $toExecuteTasks,
        Closure $handleTaskFunc
    ): void {
        $toExecuteTasks->walk(func: static function (TaskInterface $task) use ($handleTaskFunc, $toExecuteTasks): void {
            $handleTaskFunc($task, $toExecuteTasks);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'default' === $policy;
    }
}
