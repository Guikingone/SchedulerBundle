<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerStoppedEventTest extends TestCase
{
    public function testWorkerIsAccessible(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $workerStoppedEvent = new WorkerStoppedEvent($worker);
        self::assertSame($worker, $workerStoppedEvent->getWorker());
    }
}
