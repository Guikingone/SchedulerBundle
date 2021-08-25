<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutingEventTest extends TestCase
{
    public function testEventIsConfigured(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $event = new TaskExecutingEvent($task, $worker);

        self::assertSame($task, $event->getTask());
        self::assertSame($worker, $event->getWorker());
    }
}
