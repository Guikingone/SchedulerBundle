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
        $worker = $this->createMock(WorkerInterface::class);

        $pool = new WorkerRegistry([
            $worker,
        ]);

        self::assertCount(1, $pool);
    }

    public function testRegistryCanReturnWorkers(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $pool = new WorkerRegistry([
            $worker,
        ]);

        self::assertCount(1, $pool->getWorkers());
    }
}
