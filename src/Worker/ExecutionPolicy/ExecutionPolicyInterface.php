<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ExecutionPolicyInterface
{
    public function execute(WorkerConfiguration $configuration, TaskListInterface $taskList): Closure;

    public function support(string $policy): bool;
}
