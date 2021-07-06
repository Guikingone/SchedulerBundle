<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use DateTimeImmutable;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Task\WrappedTask;
use SchedulerBundle\Worker\Parallel\WorkerPoolInterface;
use function pcntl_fork;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ParallelWorker extends AbstractWorker
{
    private TaskListInterface $executedTasks;
    private WorkerPoolInterface $workerPool;

    /**
     * {@inheritdoc}
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        $this->run($options, function () use ($options, $tasks): void {
            $tasks = $this->getTasks($tasks)->filter(fn (TaskInterface $task): bool => $this->checkTaskState($task));
            if (0 === $tasks->count()) {
                return;
            }

            $tasks->walk(function (TaskInterface $task): void {
                $this->executedTasks->add($this->wrapTask($task));
            });

            $this->workerPool->boot($tasks->count());

            // TODO

            $this->workerPool->stop();
        });
    }

    private function wrapTask(TaskInterface $task): WrappedTask
    {
        $childProcessId = pcntl_fork();
        if (0 === $childProcessId) {
        }

        $wrappedTask = new WrappedTask($task, $childProcessId, new DateTimeImmutable());
    }
}
