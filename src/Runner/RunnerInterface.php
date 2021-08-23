<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Middleware\TaskExecutionMiddleware;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface RunnerInterface
{
    /**
     * Execute the @param TaskInterface $task and define an {@see Output} regarding the execution process.
     *
     * If required, the @param WorkerInterface $worker can be used to execute the task according to the needs.
     *
     * The {@see TaskInterface::setExecutionState()} method SHOULD NOT be called during the execution process as
     * the {@see TaskExecutionMiddleware::postExecute()} does the call, any call BEFORE the middleware will be
     * ignored and the execution state overridden by the middleware.
     */
    public function run(TaskInterface $task, WorkerInterface $worker): Output;

    /**
     * Determine if a @param TaskInterface $task is supported by the runner.
     *
     * The determination process is totally up to the runner.
     */
    public function support(TaskInterface $task): bool;
}
