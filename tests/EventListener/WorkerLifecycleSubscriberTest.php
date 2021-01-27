<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\EventListener\WorkerLifecycleSubscriber;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerLifecycleSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        self::assertArrayHasKey(WorkerRestartedEvent::class, WorkerLifecycleSubscriber::getSubscribedEvents());
        self::assertSame('onWorkerRestarted', WorkerLifecycleSubscriber::getSubscribedEvents()[WorkerRestartedEvent::class]);
        self::assertArrayHasKey(WorkerRunningEvent::class, WorkerLifecycleSubscriber::getSubscribedEvents());
        self::assertSame('onWorkerRunning', WorkerLifecycleSubscriber::getSubscribedEvents()[WorkerRunningEvent::class]);
        self::assertArrayHasKey(WorkerStartedEvent::class, WorkerLifecycleSubscriber::getSubscribedEvents());
        self::assertSame('onWorkerStarted', WorkerLifecycleSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);
        self::assertArrayHasKey(WorkerStoppedEvent::class, WorkerLifecycleSubscriber::getSubscribedEvents());
        self::assertSame('onWorkerStopped', WorkerLifecycleSubscriber::getSubscribedEvents()[WorkerStoppedEvent::class]);
    }

    public function testSubscriberLogOnWorkerRestarted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = $this->createMock(TaskListInterface::class);
        $list->expects(self::once())->method('count')->willReturn(0);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($list);
        $worker->expects(self::once())->method('getLastExecutedTask')->willReturn($task);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The worker has been restarted'), self::equalTo([
            'failedTasks' => 0,
            'lastExecutedTask' => 'foo',
        ]));

        $subscriber = new WorkerLifecycleSubscriber($logger);
        $subscriber->onWorkerRestarted(new WorkerRestartedEvent($worker));
    }

    public function testSubscriberLogOnWorkerRunning(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = $this->createMock(TaskListInterface::class);
        $list->expects(self::once())->method('count')->willReturn(0);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($list);
        $worker->expects(self::once())->method('getLastExecutedTask')->willReturn($task);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The worker is currently running'), self::equalTo([
            'failedTasks' => 0,
            'lastExecutedTask' => 'foo',
            'idle' => false,
        ]));

        $subscriber = new WorkerLifecycleSubscriber($logger);
        $subscriber->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testSubscriberLogOnWorkerStarted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = $this->createMock(TaskListInterface::class);
        $list->expects(self::once())->method('count')->willReturn(0);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($list);
        $worker->expects(self::once())->method('getLastExecutedTask')->willReturn($task);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The worker has been started'), self::equalTo([
            'failedTasks' => 0,
            'lastExecutedTask' => 'foo',
        ]));

        $subscriber = new WorkerLifecycleSubscriber($logger);
        $subscriber->onWorkerStarted(new WorkerStartedEvent($worker));
    }

    public function testSubscriberLogOnWorkerStopped(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = $this->createMock(TaskListInterface::class);
        $list->expects(self::once())->method('count')->willReturn(0);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($list);
        $worker->expects(self::once())->method('getLastExecutedTask')->willReturn($task);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The worker has been stopped'), self::equalTo([
            'failedTasks' => 0,
            'lastExecutedTask' => 'foo',
        ]));

        $subscriber = new WorkerLifecycleSubscriber($logger);
        $subscriber->onWorkerStopped(new WorkerStoppedEvent($worker));
    }
}
