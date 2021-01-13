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
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLoggerSubscriberTest extends TestCase
{
    public function testEventsAreSubscribed(): void
    {
        self::assertArrayHasKey(TaskScheduledEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
        self::assertContains('onTask', TaskLoggerSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);
        self::assertContains(-255, TaskLoggerSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);

        self::assertArrayHasKey(TaskFailedEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
        self::assertContains('onTask', TaskLoggerSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);
        self::assertContains(-255, TaskLoggerSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);

        self::assertArrayHasKey(TaskScheduledEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
        self::assertContains('onTask', TaskLoggerSubscriber::getSubscribedEvents()[TaskScheduledEvent::class]);
        self::assertContains(-255, TaskLoggerSubscriber::getSubscribedEvents()[TaskScheduledEvent::class]);

        self::assertArrayHasKey(TaskUnscheduledEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
        self::assertContains('onTask', TaskLoggerSubscriber::getSubscribedEvents()[TaskUnscheduledEvent::class]);
        self::assertContains(-255, TaskLoggerSubscriber::getSubscribedEvents()[TaskUnscheduledEvent::class]);
    }

    public function testScheduledTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = new TaskScheduledEvent($task);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTask($event);

        self::assertNotEmpty($subscriber->getEvents()->getEvents());
        self::assertNotEmpty($subscriber->getEvents()->getScheduledTaskEvents());
        self::assertEmpty($subscriber->getEvents()->getFailedTaskEvents());
        self::assertEmpty($subscriber->getEvents()->getExecutedTaskEvents());
        self::assertEmpty($subscriber->getEvents()->getUnscheduledTaskEvents());
    }

    public function testExecutedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = new TaskExecutedEvent($task);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTask($event);

        self::assertNotEmpty($subscriber->getEvents()->getEvents());
    }

    public function testFailedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $failedTask = new FailedTask($task, 'error');

        $event = new TaskFailedEvent($failedTask);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTask($event);

        self::assertNotEmpty($subscriber->getEvents()->getEvents());
    }
}
