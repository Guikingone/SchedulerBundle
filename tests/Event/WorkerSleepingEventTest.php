<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerSleepingEvent;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerSleepingEventTest extends TestCase
{
    public function testEventIsConfigured(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $event = new WorkerSleepingEvent(1, $worker);

        self::assertSame(1, $event->getSleepDuration());
        self::assertSame($worker, $event->getWorker());
    }
}
