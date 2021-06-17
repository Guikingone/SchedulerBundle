<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface RunnerInterface
{
    /**
     * Run a @param TaskInterface $task, the {@see WorkerInterface} can be used if required.
     */
    public function run(TaskInterface $task, WorkerInterface $worker): Output;

    /**
     * Determine if the runner can run the @param TaskInterface $task
     */
    public function support(TaskInterface $task): bool;
}
