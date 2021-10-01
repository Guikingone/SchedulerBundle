<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedTaskRunner implements RunnerInterface
{
    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task, WorkerInterface $worker): Output
    {
        if (!$task instanceof ChainedTask) {
            return new Output($task, null, Output::ERROR);
        }

        $forkedWorker = $worker->fork();

        try {
            $task->getTasks()->walk(static function (TaskInterface $task) use ($forkedWorker): void {
                $forkedWorker->execute(WorkerConfiguration::create(), $task);
            });
        } catch (Throwable $throwable) {
            return new Output($task, $throwable->getMessage(), Output::ERROR);
        } finally {
            $forkedWorker->stop();
        }

        return new Output($task, null, Output::SUCCESS);
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof ChainedTask;
    }
}
