<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Runner;

use SchedulerBundle\Task\CallbackTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
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
            $output = \call_user_func_array($task->getCallback(), $task->getArguments());

            if (false === $output) {
                $task->setExecutionState(TaskInterface::ERRORED);

                return new Output($task, null, Output::ERROR);
            }

            $task->setExecutionState(TaskInterface::SUCCEED);

            return new Output($task, trim($output));
        } catch (\Throwable $throwable) {
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
