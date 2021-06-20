<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use Generator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\EventListener\StopWorkerOnTimeLimitSubscriber;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use function sleep;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnTimeLimitSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        self::assertArrayHasKey(WorkerStartedEvent::class, StopWorkerOnTimeLimitSubscriber::getSubscribedEvents());
        self::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnTimeLimitSubscriber::getSubscribedEvents());
    }

    /**
     * @dataProvider provideTimeLimit
     *
     * @group time-sensitive
     */
    public function testSubscriberCannotStopWithoutExceededTimeLimit(int $timeLimit): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');
        $worker->expects(self::never())->method('getLastExecutedTask');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $stopWorkerOnTimeLimitSubscriber = new StopWorkerOnTimeLimitSubscriber($timeLimit, $logger);
        $workerRunningEvent = new WorkerRunningEvent($worker);

        $stopWorkerOnTimeLimitSubscriber->onWorkerStarted();
        $stopWorkerOnTimeLimitSubscriber->onWorkerRunning($workerRunningEvent);
    }

    /**
     * @dataProvider provideTimeLimit
     *
     * @group time-sensitive
     */
    public function testSubscriberCanStopOnExceededTimeLimit(int $timeLimit): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');
        $worker->expects(self::once())->method('getLastExecutedTask')->willReturn($task);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            self::equalTo(sprintf('Worker stopped due to time limit of %d seconds exceeded', $timeLimit)),
            self::equalTo([
                'lastExecutedTask' => 'foo',
            ])
        );

        $stopWorkerOnTimeLimitSubscriber = new StopWorkerOnTimeLimitSubscriber($timeLimit, $logger);
        $workerRunningEvent = new WorkerRunningEvent($worker);

        $stopWorkerOnTimeLimitSubscriber->onWorkerStarted();
        sleep($timeLimit + 1);
        $stopWorkerOnTimeLimitSubscriber->onWorkerRunning($workerRunningEvent);
    }

    /**
     * @dataProvider provideTimeLimit
     *
     * @group time-sensitive
     */
    public function testSubscriberCanStopOnExceededTimeLimitWithoutLastExecutedTask(int $timeLimit): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');
        $worker->expects(self::once())->method('getLastExecutedTask')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            self::equalTo(sprintf('Worker stopped due to time limit of %d seconds exceeded', $timeLimit)),
            self::equalTo([
                'lastExecutedTask' => null,
            ])
        );

        $stopWorkerOnTimeLimitSubscriber = new StopWorkerOnTimeLimitSubscriber($timeLimit, $logger);
        $workerRunningEvent = new WorkerRunningEvent($worker);

        $stopWorkerOnTimeLimitSubscriber->onWorkerStarted();
        sleep($timeLimit + 1);
        $stopWorkerOnTimeLimitSubscriber->onWorkerRunning($workerRunningEvent);
    }

    /**
     * @return Generator<array<int, int>>
     */
    public function provideTimeLimit(): Generator
    {
        yield [1];
        yield [2];
    }
}
