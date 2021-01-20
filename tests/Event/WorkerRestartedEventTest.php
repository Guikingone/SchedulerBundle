<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerRestartedEventTest extends TestCase
{
    public function testWorkerCanBeRetrieved(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $event = new WorkerRestartedEvent($worker);

        self::assertSame($worker, $event->getWorker());
    }
}
