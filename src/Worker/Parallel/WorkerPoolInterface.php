<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\Parallel;

use Countable;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkerPoolInterface extends Countable
{
    public function boot(WorkerInterface $worker, int $subWorkerAmount): void;

    /**
     * Run a @param TaskListInterface $taskList in by dividing the load into multiple workers.
     *
     * Once each task has run, the list of failed task is return via a {@see TaskListInterface}
     */
    public function run(TaskListInterface $taskList): TaskListInterface;

    public function scaleUp(int $newSubWorkerAmount): void;

    public function scaleDown(int $newSubWorkerAmount): void;

    public function stop(): void;
}
