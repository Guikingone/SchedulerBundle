<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedTaskRunner implements RunnerInterface
{
    /**
     * @var iterable|RunnerInterface[]
     */
    private $runners;

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
    public function run(TaskInterface $task): Output
    {
        if (!$task instanceof ChainedTask) {
            $task->setExecutionState(TaskInterface::ERRORED);
            return new Output($task, null, Output::ERROR);
        }

        try {
            foreach ($task->getTasks() as $chainedTask) {
                array_walk($this->runners, function (RunnerInterface $runner) use ($chainedTask): void {
                    if ($runner->support($chainedTask)) {
                        $runner->run($chainedTask);
                    }
                });
            }
        } catch (Throwable $exception) {
            $task->setExecutionState(TaskInterface::ERRORED);
            return new Output($task, $exception->getMessage(), Output::ERROR);
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
