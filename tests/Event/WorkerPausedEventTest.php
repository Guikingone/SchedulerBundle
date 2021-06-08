<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerPausedEvent;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerPausedEventTest extends TestCase
{
    public function testEventCanReturnWorker(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $event = new WorkerPausedEvent($worker);

        self::assertSame($worker, $event->getWorker());
    }
}
