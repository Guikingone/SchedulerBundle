<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\EventListener\StopWorkerOnFailureLimitSubscriber;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnFailureLimitSubscriberTest extends TestCase
{
    public function testSubscriberListenValidEvents(): void
    {
        self::assertArrayHasKey(TaskFailedEvent::class, StopWorkerOnFailureLimitSubscriber::getSubscribedEvents());
        self::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnFailureLimitSubscriber::getSubscribedEvents());
    }

    public function testSubscriberCannotStopWorkerWhenIdle(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $workerStartedEvent = new WorkerRunningEvent($worker, false);

        $subscriber = new StopWorkerOnFailureLimitSubscriber(0, $logger);
        $subscriber->onTaskFailedEvent();
        $subscriber->onWorkerStarted($workerStartedEvent);
    }

    public function testSubscriberCannotStopWorkerWhenLimitNotReached(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $workerStartedEvent = new WorkerRunningEvent($worker, true);

        $subscriber = new StopWorkerOnFailureLimitSubscriber(2, $logger);
        $subscriber->onTaskFailedEvent();
        $subscriber->onWorkerStarted($workerStartedEvent);
    }

    public function testSubscriberCannotStopWorkerWhenWorkerIsNotIdleButLimitIsReached(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $workerStartedEvent = new WorkerRunningEvent($worker, false);

        $subscriber = new StopWorkerOnFailureLimitSubscriber(0, $logger);
        $subscriber->onTaskFailedEvent();
        $subscriber->onWorkerStarted($workerStartedEvent);
    }

    public function testSubscriberCanStopWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('Worker has stopped due to the failure limit of 0 exceeded'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');

        $workerStartedEvent = new WorkerRunningEvent($worker, true);

        $subscriber = new StopWorkerOnFailureLimitSubscriber(0, $logger);
        $subscriber->onTaskFailedEvent();
        $subscriber->onWorkerStarted($workerStartedEvent);
    }
}
