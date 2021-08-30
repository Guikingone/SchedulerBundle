<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\EventListener\TaskLifecycleSubscriber;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLifecycleSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        self::assertArrayHasKey(TaskScheduledEvent::class, TaskLifecycleSubscriber::getSubscribedEvents());
        self::assertSame('onTaskScheduled', TaskLifecycleSubscriber::getSubscribedEvents()[TaskScheduledEvent::class]);
        self::assertArrayHasKey(TaskUnscheduledEvent::class, TaskLifecycleSubscriber::getSubscribedEvents());
        self::assertSame('onTaskUnscheduled', TaskLifecycleSubscriber::getSubscribedEvents()[TaskUnscheduledEvent::class]);
        self::assertArrayHasKey(TaskExecutedEvent::class, TaskLifecycleSubscriber::getSubscribedEvents());
        self::assertSame('onTaskExecuted', TaskLifecycleSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);
        self::assertArrayHasKey(TaskFailedEvent::class, TaskLifecycleSubscriber::getSubscribedEvents());
        self::assertSame('onTaskFailed', TaskLifecycleSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);
    }

    public function testSubscriberCanLogWhenATaskIsScheduled(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('A task has been scheduled'), self::equalTo([
            'task' => 'foo',
        ]));

        $taskLifecycleSubscriber = new TaskLifecycleSubscriber($logger);
        $taskLifecycleSubscriber->onTaskScheduled(new TaskScheduledEvent($task));
    }

    public function testSubscriberCanLogWhenATaskIsUnscheduled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('A task has been unscheduled'), self::equalTo([
            'task' => 'foo',
        ]));

        $taskLifecycleSubscriber = new TaskLifecycleSubscriber($logger);
        $taskLifecycleSubscriber->onTaskUnscheduled(new TaskUnscheduledEvent('foo'));
    }

    public function testSubscriberCanLogWhenATaskIsExecuted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('A task has been executed'), self::equalTo([
            'task' => 'foo',
        ]));

        $taskLifecycleSubscriber = new TaskLifecycleSubscriber($logger);
        $taskLifecycleSubscriber->onTaskExecuted(new TaskExecutedEvent($task, new Output($task)));
    }

    public function testSubscriberCanLogWhenATaskHasFailed(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::equalTo('A task execution has failed'), self::equalTo([
            'task' => 'foo',
        ]));

        $taskLifecycleSubscriber = new TaskLifecycleSubscriber($logger);
        $taskLifecycleSubscriber->onTaskFailed(new TaskFailedEvent(new FailedTask($task, 'random error')));
    }
}
