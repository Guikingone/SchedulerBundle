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
final class FiberPolicy implements ExecutionPolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(WorkerConfiguration $configuration, TaskListInterface $taskList): Closure
    {
        return static function (WorkerInterface $worker, TaskListInterface $taskList): void {};
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'fiber' === $policy;
    }
}
