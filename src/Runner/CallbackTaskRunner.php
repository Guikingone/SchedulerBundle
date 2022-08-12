<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Task\CallbackTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

use function call_user_func_array;
use function trim;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallbackTaskRunner implements RunnerInterface
{
    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task, WorkerInterface $worker): Output
    {
        if (!$task instanceof CallbackTask) {
            return new Output(task: $task, output: null, type: Output::ERROR);
        }

        try {
            $output = call_user_func_array($task->getCallback(), $task->getArguments());

            if (false === $output) {
                return new Output(task: $task, output: null, type: Output::ERROR);
            }

            return new Output(task: $task, output: trim((string) $output));
        } catch (Throwable) {
            return new Output(task: $task, output: null, type: Output::ERROR);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof CallbackTask;
    }
}
