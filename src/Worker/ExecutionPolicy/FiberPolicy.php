<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Fiber;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberPolicy implements ExecutionPolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(
        TaskListInterface $toExecuteTasks,
        Closure $handleTaskFunc
    ): void {
        $toExecuteTasks->walk(function (TaskInterface $task) use ($toExecuteTasks, $handleTaskFunc): void {
            $fiber = new Fiber(function (TaskInterface $toExecuteTask) use ($toExecuteTasks, $handleTaskFunc): void {
                $handleTaskFunc($toExecuteTask, $toExecuteTasks);
            });

            $fiber->start($task, $toExecuteTasks);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'fiber' === $policy;
    }
}
