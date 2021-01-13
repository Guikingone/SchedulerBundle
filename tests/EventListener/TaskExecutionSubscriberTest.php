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
use SchedulerBundle\Event\SingleRunTaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\EventListener\TaskExecutionSubscriber;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutionSubscriberTest extends TestCase
{
    public function testSubscriberListenValidEvent(): void
    {
        static::assertArrayHasKey(SingleRunTaskExecutedEvent::class, TaskExecutionSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskExecutedEvent::class, TaskExecutionSubscriber::getSubscribedEvents());
    }

    public function testSubscriberCanUnscheduleSingleRunTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule')->with(self::equalTo('foo'));

        $event = new SingleRunTaskExecutedEvent($task);

        $subscriber = new TaskExecutionSubscriber($scheduler);
        $subscriber->onSingleRunTaskExecuted($event);
    }

    public function testSubscriberCanUpdateExecutedTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('update')->with(self::equalTo('foo'), $task);

        $event = new TaskExecutedEvent($task);

        $subscriber = new TaskExecutionSubscriber($scheduler);
        $subscriber->onTaskExecuted($event);
    }
}
