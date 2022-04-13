<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DefaultPolicy implements ExecutionPolicyInterface
{
    public function execute(WorkerConfiguration $configuration, TaskListInterface $taskList): Closure
    {
        return static function (WorkerInterface $worker, TaskListInterface $taskList): void {
            while (!$worker->getConfiguration()->shouldStop()) {
                if (0 === $taskList->count() && !$worker->getConfiguration()->isSleepingUntilNextMinute()) {
                    $worker->stop();
                }

                $taskList->walk(static function () use ($worker, $taskList): void {
                    $worker->execute(
                        $worker->getConfiguration(),
                        $taskList->toArray(false)
                    );
                });

                if ($worker->getConfiguration()->shouldStop()) {
                    break;
                }

                if ($worker->getConfiguration()->isSleepingUntilNextMinute()) {
                    $worker->sleep();
                    $this->execute($worker->getConfiguration(), $taskList->toArray(false));
                }
            }
        };
    }

    public function support(string $policy): bool
    {
        return 'default' === $policy;
    }
}
