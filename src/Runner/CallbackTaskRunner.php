<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Task\CallbackTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
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
    public function run(TaskInterface $task): Output
    {
        $task->setExecutionState(TaskInterface::RUNNING);

        try {
            $output = call_user_func_array($task->getCallback(), $task->getArguments());

            if (false === $output) {
                $task->setExecutionState(TaskInterface::ERRORED);

                return new Output($task, null, Output::ERROR);
            }

            $task->setExecutionState(TaskInterface::SUCCEED);

            return new Output($task, trim((string) $output));
        } catch (Throwable $throwable) {
            $task->setExecutionState(TaskInterface::ERRORED);

            return new Output($task, null, Output::ERROR);
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
