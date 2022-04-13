<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DefaultPolicy implements ExecutionPolicyInterface
{
    public function execute(
        WorkerInterface $worker,
        Closure $fetchTaskListFunc,
        Closure $handleTaskFunc
    ): void {
        while (!$worker->getConfiguration()->shouldStop()) {
            $tasks = $fetchTaskListFunc();
            if (0 === $tasks->count() && !$worker->getConfiguration()->isSleepingUntilNextMinute()) {
                $worker->stop();
            }

            $tasks->walk(static function (TaskInterface $task) use ($worker, $handleTaskFunc, $tasks): void {
                $handleTaskFunc($task, $tasks);
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

    public function support(string $policy): bool
    {
        return 'default' === $policy;
    }
}
