<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\EventListener\StopWorkerOnTimeLimitSubscriber;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnTimeLimitSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        static::assertArrayHasKey(WorkerStartedEvent::class, StopWorkerOnTimeLimitSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnTimeLimitSubscriber::getSubscribedEvents());
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

        $subscriber = new StopWorkerOnTimeLimitSubscriber($timeLimit, $logger);
        $event = new WorkerRunningEvent($worker);

        $subscriber->onWorkerStarted();
        $subscriber->onWorkerRunning($event);
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
        $worker->expects(self::exactly(2))->method('getLastExecutedTask')->willReturn($task);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            self::equalTo(sprintf('Worker stopped due to time limit of %d seconds exceeded', $timeLimit)),
            [
                'lastExecutedTask' => 'foo',
            ]
        );

        $subscriber = new StopWorkerOnTimeLimitSubscriber($timeLimit, $logger);
        $event = new WorkerRunningEvent($worker);

        $subscriber->onWorkerStarted();
        sleep($timeLimit + 1);
        $subscriber->onWorkerRunning($event);
    }

    public function provideTimeLimit(): \Generator
    {
        yield [1];
        yield [2];
    }
}
