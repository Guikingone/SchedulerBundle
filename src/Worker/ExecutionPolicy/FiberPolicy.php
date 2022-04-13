<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberPolicy implements ExecutionPolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(
        WorkerInterface $worker,
        Closure $fetchTaskListFunc,
        Closure $handleTaskFunc
    ): void {
        while (!$worker->getConfiguration()->shouldStop()) {
            $toExecuteTasks = $fetchTaskListFunc();
            if (0 === $toExecuteTasks->count() && !$worker->getConfiguration()->isSleepingUntilNextMinute()) {
                $worker->stop();
            }

            $toExecuteTasks->walk(function (TaskInterface $task) use ($toExecuteTasks, $handleTaskFunc): void {
                $fiber = new Fiber(function (TaskInterface $toExecuteTask) use ($toExecuteTasks, $handleTaskFunc): void {
                    $handleTaskFunc($toExecuteTask, $toExecuteTasks);
                });

                $fiber->start($task, $toExecuteTasks);
            });

            if ($worker->getConfiguration()->shouldStop()) {
                break;
            }

            if ($worker->getConfiguration()->isSleepingUntilNextMinute()) {
                $worker->sleep();
                $this->execute($worker, $fetchTaskListFunc, $handleTaskFunc);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'fiber' === $policy;
    }
}
