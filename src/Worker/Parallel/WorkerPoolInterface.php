<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\Parallel;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkerPoolInterface
{
    public function boot(int $subWorkerAmount): void;

    public function scaleUp(int $newSubWorkerAmount): void;

    public function scaleDown(int $newSubWorkerAmount): void;

    public function stop(): void;
}
