<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerStartedEventTest extends TestCase
{
    public function testEventReturnWorkerState(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $workerStartedEvent = new WorkerStartedEvent($worker);

        self::assertSame($worker, $workerStartedEvent->getWorker());
    }
}
