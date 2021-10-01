<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutingEventTest extends TestCase
{
    public function testEventIsConfigured(): void
    {
        $list = new TaskList();
        $task = new NullTask('foo');
        $worker = $this->createMock(WorkerInterface::class);

        $event = new TaskExecutingEvent($task, $worker, $list);

        self::assertSame($task, $event->getTask());
        self::assertSame($worker, $event->getWorker());
        self::assertSame($list, $event->getCurrentTasks());
    }
}
