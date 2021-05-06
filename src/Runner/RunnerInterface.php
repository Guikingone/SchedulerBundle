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
    public function run(TaskInterface $task, WorkerInterface $worker): Output;

    /**
     * Determine if a @param TaskInterface $task is supported by the runner.
     *
     * The determination process is totally up to the runner.
     */
    public function support(TaskInterface $task): bool;
}
