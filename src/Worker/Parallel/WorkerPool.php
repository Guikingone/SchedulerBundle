<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\Parallel;

use Psr\Log\LoggerInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;
use function array_walk;
use function count;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerPool implements WorkerPoolInterface
{
    /**
     * @var WorkerInterface[]
     */
    private array $workers;
    private WorkerInterface $worker;
    private LoggerInterface $logger;

    /**
     * {@inheritdoc}
     */
    public function boot(WorkerInterface $worker, int $subWorkerAmount): void
    {
        $this->worker = $worker;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskListInterface $taskList): TaskListInterface
    {
    }

    /**
     * {@inheritdoc}
     */
    public function scaleUp(int $newSubWorkerAmount): void
    {
        while ($this->count() < $newSubWorkerAmount) {
            $this->workers[] = $this->worker->fork();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function scaleDown(int $newSubWorkerAmount): void
    {
        while ($this->count() > $newSubWorkerAmount) {
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        array_walk($this->workers, function (WorkerInterface $worker): void {
            $worker->stop();
        });

        $this->workers = [];
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->workers);
    }
}
