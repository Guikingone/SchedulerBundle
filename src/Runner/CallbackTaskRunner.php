<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Task\CallbackTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use Throwable;
use function call_user_func_array;
use function is_string;
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
        if (!$task instanceof CallbackTask) {
            $task->setExecutionState(TaskInterface::ERRORED);

            return new Output($task, null, Output::ERROR);
        }

        try {
            $output = call_user_func_array($task->getCallback(), $task->getArguments());

            if (false === $output) {
                $task->setExecutionState(TaskInterface::ERRORED);

                return new Output($task, null, Output::ERROR);
            }

            $task->setExecutionState(TaskInterface::SUCCEED);

            return new Output($task, trim(!is_string($output) ? (string) $output : $output));
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
