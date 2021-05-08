<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedTaskRunner implements RunnerInterface
{
    /**
     * @var iterable|RunnerInterface[]
     */
    private iterable $runners;

    /**
     * @param iterable|RunnerInterface[] $runners
     */
    public function __construct(iterable $runners)
    {
        $this->runners = $runners;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task, WorkerInterface $worker): Output
    {
        if (!$task instanceof ChainedTask) {
            $task->setExecutionState(TaskInterface::ERRORED);
            return new Output($task, null, Output::ERROR);
        }

        try {
            $worker->execute($worker->getOptions(), ...$task->getTasks());
        } catch (Throwable $throwable) {
            $task->setExecutionState(TaskInterface::ERRORED);
            return new Output($task, $throwable->getMessage(), Output::ERROR);
        }

        $task->setExecutionState(TaskInterface::SUCCEED);
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
