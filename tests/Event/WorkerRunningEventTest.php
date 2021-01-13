<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class WorkerRunningEventTest extends TestCase
{
    public function testWorkerCanBeRetrieved(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $event = new WorkerRunningEvent($worker);
        self::assertSame($worker, $event->getWorker());
    }
}
