<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
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
        static::assertArrayHasKey(TaskScheduledEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskExecutedEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
    }

    public function testScheduledTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = new TaskScheduledEvent($task);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTask($event);

        static::assertNotEmpty($subscriber->getEvents()->getEvents());
        static::assertNotEmpty($subscriber->getEvents()->getScheduledTaskEvents());
        static::assertEmpty($subscriber->getEvents()->getFailedTaskEvents());
        static::assertEmpty($subscriber->getEvents()->getExecutedTaskEvents());
        static::assertEmpty($subscriber->getEvents()->getUnscheduledTaskEvents());
    }

    public function testExecutedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = new TaskExecutedEvent($task);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTask($event);

        static::assertNotEmpty($subscriber->getEvents()->getEvents());
    }

    public function testFailedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $failedTask = new FailedTask($task, 'error');

        $event = new TaskFailedEvent($failedTask);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTask($event);

        static::assertNotEmpty($subscriber->getEvents()->getEvents());
    }
}
