<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\EventListener\StopWorkerOnFailureLimitSubscriber;
use SchedulerBundle\Exception\InvalidArgumentException;
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

    public function testSubscriberCannotUseNegativeLimit(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectErrorMessage('The failure limit must be greater than 0, given -1');
        self::expectExceptionCode(0);
        new StopWorkerOnFailureLimitSubscriber(-1);
    }

    public function testSubscriberCannotUseZeroLimit(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectErrorMessage('The failure limit must be greater than 0, given 0');
        self::expectExceptionCode(0);
        new StopWorkerOnFailureLimitSubscriber(0);
    }

    public function testSubscriberCannotStopWorkerWhenIdle(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $workerRunningEvent = new WorkerRunningEvent($worker, false);

        $stopWorkerOnFailureLimitSubscriber = new StopWorkerOnFailureLimitSubscriber(1, $logger);
        $stopWorkerOnFailureLimitSubscriber->onTaskFailedEvent();
        $stopWorkerOnFailureLimitSubscriber->onWorkerStarted($workerRunningEvent);
    }

    public function testSubscriberCannotStopWorkerWhenTaskFailedLimitNotIncremented(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $workerRunningEvent = new WorkerRunningEvent($worker, true);

        $stopWorkerOnFailureLimitSubscriber = new StopWorkerOnFailureLimitSubscriber(2, $logger);
        $stopWorkerOnFailureLimitSubscriber->onWorkerStarted($workerRunningEvent);
    }

    public function testSubscriberCannotStopWorkerWhenLimitNotReached(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $workerRunningEvent = new WorkerRunningEvent($worker, true);

        $stopWorkerOnFailureLimitSubscriber = new StopWorkerOnFailureLimitSubscriber(2, $logger);
        $stopWorkerOnFailureLimitSubscriber->onTaskFailedEvent();
        $stopWorkerOnFailureLimitSubscriber->onWorkerStarted($workerRunningEvent);
    }

    public function testSubscriberCannotStopWorkerWhenWorkerIsNotIdleButLimitIsReached(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $workerRunningEvent = new WorkerRunningEvent($worker, false);

        $stopWorkerOnFailureLimitSubscriber = new StopWorkerOnFailureLimitSubscriber(1, $logger);
        $stopWorkerOnFailureLimitSubscriber->onTaskFailedEvent();
        $stopWorkerOnFailureLimitSubscriber->onWorkerStarted($workerRunningEvent);
    }

    public function testSubscriberCanStopWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')
            ->withConsecutive(
                [self::equalTo('Worker has stopped due to the failure limit of 1 exceeded')],
                [self::equalTo('Failure limit back to: 0')]
            )
        ;

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');

        $workerRunningEvent = new WorkerRunningEvent($worker, true);

        $stopWorkerOnFailureLimitSubscriber = new StopWorkerOnFailureLimitSubscriber(1, $logger);
        $stopWorkerOnFailureLimitSubscriber->onTaskFailedEvent();
        $stopWorkerOnFailureLimitSubscriber->onTaskFailedEvent();
        $stopWorkerOnFailureLimitSubscriber->onWorkerStarted($workerRunningEvent);
    }

    public function testSubscriberCanStopWorkerOnEqualFailedTask(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')
            ->withConsecutive(
                [self::equalTo('Worker has stopped due to the failure limit of 1 exceeded')],
                [self::equalTo('Failure limit back to: 0')]
            )
        ;

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');

        $workerRunningEvent = new WorkerRunningEvent($worker, true);

        $stopWorkerOnFailureLimitSubscriber = new StopWorkerOnFailureLimitSubscriber(1, $logger);
        $stopWorkerOnFailureLimitSubscriber->onTaskFailedEvent();
        $stopWorkerOnFailureLimitSubscriber->onWorkerStarted($workerRunningEvent);
    }

    public function testSubscriberCanStopWorkerOnExtraFailedTask(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')
            ->withConsecutive(
                [self::equalTo('Worker has stopped due to the failure limit of 1 exceeded')],
                [self::equalTo('Failure limit back to: 0')]
            );

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');

        $workerRunningEvent = new WorkerRunningEvent($worker, true);

        $stopWorkerOnFailureLimitSubscriber = new StopWorkerOnFailureLimitSubscriber(1, $logger);
        $stopWorkerOnFailureLimitSubscriber->onTaskFailedEvent();
        $stopWorkerOnFailureLimitSubscriber->onTaskFailedEvent();
        $stopWorkerOnFailureLimitSubscriber->onWorkerStarted($workerRunningEvent);
    }

    public function testSubscriberCannotStopWorkerTwiceWithoutReachingTheLimit(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')
            ->withConsecutive(
                [self::equalTo('Worker has stopped due to the failure limit of 2 exceeded')],
                [self::equalTo('Failure limit back to: 0')]
            );

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');

        $workerRunningEvent = new WorkerRunningEvent($worker, true);

        $stopWorkerOnFailureLimitSubscriber = new StopWorkerOnFailureLimitSubscriber(2, $logger);
        $stopWorkerOnFailureLimitSubscriber->onTaskFailedEvent();
        $stopWorkerOnFailureLimitSubscriber->onTaskFailedEvent();
        $stopWorkerOnFailureLimitSubscriber->onWorkerStarted($workerRunningEvent);

        $stopWorkerOnFailureLimitSubscriber->onWorkerStarted($workerRunningEvent);
    }
}
