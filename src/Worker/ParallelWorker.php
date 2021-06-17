<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\Parallel\WorkerPoolInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ParallelWorker extends AbstractWorker
{
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

            $this->workerPool->boot($tasks->count());

            // TODO

            $this->workerPool->stop();
        });
    }
}
