<?php

declare(strict_types=1);

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
        self::assertArrayHasKey(SingleRunTaskExecutedEvent::class, TaskExecutionSubscriber::getSubscribedEvents());
        self::assertArrayHasKey(TaskExecutedEvent::class, TaskExecutionSubscriber::getSubscribedEvents());
    }

    public function testSubscriberCanUnscheduleSingleRunTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('pause')->with(self::equalTo('foo'));

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
