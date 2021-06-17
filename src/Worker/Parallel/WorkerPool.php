<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\Parallel;

use SchedulerBundle\Worker\WorkerInterface;
use function array_walk;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerPool implements WorkerPoolInterface
{
    /**
     * @var WorkerInterface[]
     */
    private array $workers;

    /**
     * {@inheritdoc}
     */
    public function boot(int $subWorkerAmount): void
    {
        // TODO: Implement boot() method.
    }

    /**
     * {@inheritdoc}
     */
    public function scaleUp(int $newSubWorkerAmount): void
    {
        // TODO: Implement scaleUp() method.
    }

    /**
     * {@inheritdoc}
     */
    public function scaleDown(int $newSubWorkerAmount): void
    {
        // TODO: Implement scaleDown() method.
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
}
