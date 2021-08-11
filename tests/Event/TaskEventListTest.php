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
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskEventListTest extends TestCase
{
    public function testEventCanBeAdded(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskExecutedEvent($task, new Output($task)));

        self::assertCount(1, $taskEventList->getEvents());
        self::assertSame(1, $taskEventList->count());
        self::assertEmpty($taskEventList->getScheduledTaskEvents());
        self::assertEmpty($taskEventList->getFailedTaskEvents());
        self::assertCount(1, $taskEventList->getExecutedTaskEvents());
        self::assertEmpty($taskEventList->getUnscheduledTaskEvents());
        self::assertEmpty($taskEventList->getQueuedTaskEvents());
    }

    public function testScheduledTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskScheduledEvent($task));

        self::assertCount(1, $taskEventList->getScheduledTaskEvents());
        self::assertSame($task, $taskEventList->getScheduledTaskEvents()[0]->getTask());
    }

    public function testUnscheduledTaskEventsCanBeRetrieved(): void
    {
        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskUnscheduledEvent('foo'));

        self::assertCount(1, $taskEventList->getUnscheduledTaskEvents());
        self::assertSame('foo', $taskEventList->getUnscheduledTaskEvents()[0]->getTask());
    }

    public function testExecutedTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskExecutedEvent($task, new Output($task)));

        self::assertCount(1, $taskEventList->getExecutedTaskEvents());
        self::assertSame($task, $taskEventList->getExecutedTaskEvents()[0]->getTask());
    }

    public function testFailedTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $failedTask = new FailedTask($task, 'error');

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskFailedEvent($failedTask));

        self::assertCount(1, $taskEventList->getFailedTaskEvents());
        self::assertSame($failedTask, $taskEventList->getFailedTaskEvents()[0]->getTask());
        self::assertSame($task, $taskEventList->getFailedTaskEvents()[0]->getTask()->getTask());
    }

    public function testQueuedTaskEventsCanBeRetrievedWithoutValidEvent(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('isQueued')->willReturn(true);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskExecutedEvent($task, new Output($task)));

        self::assertCount(0, $taskEventList->getQueuedTaskEvents());
    }

    public function testQueuedTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('isQueued')->willReturn(true);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskScheduledEvent($task));

        self::assertCount(1, $taskEventList->getQueuedTaskEvents());
        self::assertSame($task, $taskEventList->getQueuedTaskEvents()[0]->getTask());
    }

    public function testProbeTaskEventsCanBeRetrieved(): void
    {
        $task = new ProbeTask('foo', 'https://www.foo.com/_probe');
        $nullTask = new NullTask('bar');

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskScheduledEvent($task));
        $taskEventList->addEvent(new TaskExecutedEvent($task, new Output($task)));
        $taskEventList->addEvent(new TaskScheduledEvent($nullTask));

        self::assertCount(1, $taskEventList->getProbeTaskEvents());
        self::assertSame($task, $taskEventList->getProbeTaskEvents()[0]->getTask());
    }
}
