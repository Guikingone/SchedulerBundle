<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskEventListTest extends TestCase
{
    public function testEventCanBeAdded(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $list = new TaskEventList();
        $list->addEvent(new TaskExecutedEvent($task));

        self::assertNotEmpty($list->getEvents());
        self::assertSame(1, $list->count());
        self::assertEmpty($list->getScheduledTaskEvents());
        self::assertEmpty($list->getFailedTaskEvents());
        self::assertNotEmpty($list->getExecutedTaskEvents());
        self::assertEmpty($list->getUnscheduledTaskEvents());
        self::assertEmpty($list->getQueuedTaskEvents());
    }

    public function testScheduledTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $list = new TaskEventList();
        $list->addEvent(new TaskScheduledEvent($task));

        self::assertNotEmpty($list->getScheduledTaskEvents());
        self::assertSame($task, $list->getScheduledTaskEvents()[0]->getTask());
    }

    public function testUnscheduledTaskEventsCanBeRetrieved(): void
    {
        $list = new TaskEventList();
        $list->addEvent(new TaskUnscheduledEvent('foo'));

        self::assertNotEmpty($list->getUnscheduledTaskEvents());
        self::assertSame('foo', $list->getUnscheduledTaskEvents()[0]->getTask());
    }

    public function testExecutedTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $list = new TaskEventList();
        $list->addEvent(new TaskExecutedEvent($task));

        self::assertNotEmpty($list->getExecutedTaskEvents());
        self::assertSame($task, $list->getExecutedTaskEvents()[0]->getTask());
    }

    public function testFailedTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $failedTask = new FailedTask($task, 'error');

        $list = new TaskEventList();
        $list->addEvent(new TaskFailedEvent($failedTask));

        self::assertNotEmpty($list->getFailedTaskEvents());
        self::assertSame($failedTask, $list->getFailedTaskEvents()[0]->getTask());
        self::assertSame($task, $list->getFailedTaskEvents()[0]->getTask()->getTask());
    }

    public function testQueuedTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('isQueued')->willReturn(true);

        $list = new TaskEventList();
        $list->addEvent(new TaskScheduledEvent($task));

        self::assertNotEmpty($list->getQueuedTaskEvents());
        self::assertSame($task, $list->getQueuedTaskEvents()[0]->getTask());
    }
}
