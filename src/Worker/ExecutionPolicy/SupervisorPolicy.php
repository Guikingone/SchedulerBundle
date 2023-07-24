<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\Supervisor\WorkerSupervisorInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SupervisorPolicy implements ExecutionPolicyInterface
{
    public function __construct(private WorkerSupervisorInterface $supervisor)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function execute(
        TaskListInterface $toExecuteTasks,
        Closure $handleTaskFunc
    ): void {
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'supervisor' === $policy;
    }
}
