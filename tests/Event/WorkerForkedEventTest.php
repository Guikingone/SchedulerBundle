<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerForkedEvent;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerForkedEventTest extends TestCase
{
    public function testEventReturnWorker(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $newWorker = $this->createMock(WorkerInterface::class);

        $event = new WorkerForkedEvent($worker, $newWorker);

        self::assertSame($worker, $event->getForkedWorker());
        self::assertSame($newWorker, $event->getNewWorker());
    }
}
