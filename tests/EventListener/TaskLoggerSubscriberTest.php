<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\EventListener\TaskLoggerSubscriber;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Test\Constraint\TaskQueued;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLoggerSubscriberTest extends TestCase
{
    public function testEventsAreSubscribed(): void
    {
        self::assertArrayHasKey(TaskScheduledEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
        self::assertIsArray(TaskLoggerSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);
        self::assertContains('onTask', TaskLoggerSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);
        self::assertContains(-255, TaskLoggerSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);

        self::assertArrayHasKey(TaskFailedEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
        self::assertIsArray(TaskLoggerSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);
        self::assertContains('onTask', TaskLoggerSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);
        self::assertContains(-255, TaskLoggerSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);

        self::assertArrayHasKey(TaskQueued::class, TaskLoggerSubscriber::getSubscribedEvents());
        self::assertIsArray(TaskLoggerSubscriber::getSubscribedEvents()[TaskQueued::class]);
        self::assertContains('onTask', TaskLoggerSubscriber::getSubscribedEvents()[TaskQueued::class]);
        self::assertContains(-255, TaskLoggerSubscriber::getSubscribedEvents()[TaskQueued::class]);

        self::assertArrayHasKey(TaskScheduledEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
        self::assertIsArray(TaskLoggerSubscriber::getSubscribedEvents()[TaskScheduledEvent::class]);
        self::assertContains('onTask', TaskLoggerSubscriber::getSubscribedEvents()[TaskScheduledEvent::class]);
        self::assertContains(-255, TaskLoggerSubscriber::getSubscribedEvents()[TaskScheduledEvent::class]);

        self::assertArrayHasKey(TaskUnscheduledEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
        self::assertIsArray(TaskLoggerSubscriber::getSubscribedEvents()[TaskUnscheduledEvent::class]);
        self::assertContains('onTask', TaskLoggerSubscriber::getSubscribedEvents()[TaskUnscheduledEvent::class]);
        self::assertContains(-255, TaskLoggerSubscriber::getSubscribedEvents()[TaskUnscheduledEvent::class]);
    }

    public function testScheduledTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskScheduledEvent = new TaskScheduledEvent($task);

        $taskLoggerSubscriber = new TaskLoggerSubscriber();
        $taskLoggerSubscriber->onTask($taskScheduledEvent);

        self::assertNotEmpty($taskLoggerSubscriber->getEvents()->getEvents());
        self::assertNotEmpty($taskLoggerSubscriber->getEvents()->getScheduledTaskEvents());
        self::assertEmpty($taskLoggerSubscriber->getEvents()->getFailedTaskEvents());
        self::assertEmpty($taskLoggerSubscriber->getEvents()->getExecutedTaskEvents());
        self::assertEmpty($taskLoggerSubscriber->getEvents()->getUnscheduledTaskEvents());
        self::assertEmpty($taskLoggerSubscriber->getEvents()->getQueuedTaskEvents());
    }

    public function testExecutedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskExecutedEvent = new TaskExecutedEvent($task, new Output($task));

        $taskLoggerSubscriber = new TaskLoggerSubscriber();
        $taskLoggerSubscriber->onTask($taskExecutedEvent);

        self::assertNotEmpty($taskLoggerSubscriber->getEvents()->getEvents());
    }

    public function testFailedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $failedTask = new FailedTask($task, 'error');

        $taskFailedEvent = new TaskFailedEvent($failedTask);

        $taskLoggerSubscriber = new TaskLoggerSubscriber();
        $taskLoggerSubscriber->onTask($taskFailedEvent);

        self::assertNotEmpty($taskLoggerSubscriber->getEvents()->getEvents());
    }

    public function testQueuedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('isQueued')->willReturn(true);

        $taskScheduledEvent = new TaskScheduledEvent($task);

        $taskLoggerSubscriber = new TaskLoggerSubscriber();
        $taskLoggerSubscriber->onTask($taskScheduledEvent);

        self::assertNotEmpty($taskLoggerSubscriber->getEvents()->getQueuedTaskEvents());
    }

    public function testUnscheduledTaskCanBeRetrieved(): void
    {
        $taskUnscheduledEvent = new TaskUnscheduledEvent('foo');

        $taskLoggerSubscriber = new TaskLoggerSubscriber();
        $taskLoggerSubscriber->onTask($taskUnscheduledEvent);

        self::assertNotEmpty($taskLoggerSubscriber->getEvents()->getUnscheduledTaskEvents());
    }
}
