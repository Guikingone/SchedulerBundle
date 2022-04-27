<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Worker\WorkerInterface;
use SchedulerBundle\Worker\WorkerRegistry;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerRegistryTest extends TestCase
{
    public function testRegistryCanCount(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $pool = new WorkerRegistry(workers: [
            $worker,
        ]);

        self::assertCount(expectedCount: 1, haystack: $pool);
    }

    public function testRegistryCanAddWorker(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $pool = new WorkerRegistry(workers: [
            $worker,
        ]);
        $pool->add(worker: $worker);

        self::assertCount(expectedCount: 2, haystack: $pool->getWorkers());
    }

    public function testRegistryCanReturnWorkers(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $pool = new WorkerRegistry(workers: [
            $worker,
        ]);

        self::assertCount(expectedCount: 1, haystack: $pool->getWorkers());
    }
}
