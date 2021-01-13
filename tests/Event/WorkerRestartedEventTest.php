<?php

declare(strict_types=1);

namespace SchedulerBundle\Tests\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
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
