<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTaskRunner implements RunnerInterface
{
    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        $task->setExecutionState(TaskInterface::SUCCEED);

        return new Output($task, null);
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof NullTask;
    }
}
