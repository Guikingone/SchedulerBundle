<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTaskMiddleware implements PreExecutionMiddlewareInterface
{
    private WorkerInterface $worker;

    public function __construct(WorkerInterface $worker)
    {
        $this->worker = $worker;
    }

    public function preExecute(TaskInterface $task): void
    {
        if (!$task instanceof ProbeTask) {
            return;
        }

        if (0 === $task->getDelay()) {
            return;
        }

        if ($this->worker->isRunning()) {
            return;
        }

        usleep($task->getDelay());
    }
}
