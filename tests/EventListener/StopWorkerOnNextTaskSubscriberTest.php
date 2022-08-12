<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\EventListener\StopWorkerOnNextTaskSubscriber;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function microtime;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnNextTaskSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        self::assertArrayHasKey(WorkerStartedEvent::class, StopWorkerOnNextTaskSubscriber::getSubscribedEvents());
        self::assertSame('onWorkerStarted', StopWorkerOnNextTaskSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);
        self::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnNextTaskSubscriber::getSubscribedEvents());
        self::assertSame('onWorkerRunning', StopWorkerOnNextTaskSubscriber::getSubscribedEvents()[WorkerRunningEvent::class]);
    }

    public function testSubscriberCannotStopIdleWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info')->with(self::equalTo('Worker will stop once the next task is executed'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $adapter = new ArrayAdapter();
        $adapter->get(StopWorkerOnNextTaskSubscriber::STOP_NEXT_TASK_TIMESTAMP_KEY, static fn (): float => microtime(as_float: true));

        $subscriber = new StopWorkerOnNextTaskSubscriber(stopWorkerCacheItemPool: $adapter, logger: $logger);
        $subscriber->onWorkerStarted();
        $subscriber->onWorkerRunning(new WorkerRunningEvent(worker: $worker, isIdle: true));
    }

    public function testSubscriberCannotStopRunningWorkerWithoutCacheKey(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info')->with(self::equalTo('Worker will stop once the next task is executed'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $subscriber = new StopWorkerOnNextTaskSubscriber(stopWorkerCacheItemPool: new ArrayAdapter(), logger: $logger);
        $subscriber->onWorkerStarted();
        $subscriber->onWorkerRunning(new WorkerRunningEvent(worker: $worker, isIdle: false));
    }

    public function testSubscriberCannotStopRunningWorkerOnExactSameTimestamp(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $adapter = new ArrayAdapter();
        $adapter->get(StopWorkerOnNextTaskSubscriber::STOP_NEXT_TASK_TIMESTAMP_KEY, static fn (): float => microtime(as_float: true));

        $subscriber = new StopWorkerOnNextTaskSubscriber(stopWorkerCacheItemPool: $adapter, logger: $logger);
        $subscriber->onWorkerStarted();
        $subscriber->onWorkerRunning(new WorkerRunningEvent(worker: $worker, isIdle: false));
    }

    public function testSubscriberCanStopRunningWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('Worker will stop once the next task is executed'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');

        $adapter = new ArrayAdapter();
        $adapter->get(StopWorkerOnNextTaskSubscriber::STOP_NEXT_TASK_TIMESTAMP_KEY, static fn (): float => microtime(as_float: true) + 10.00);

        $subscriber = new StopWorkerOnNextTaskSubscriber(stopWorkerCacheItemPool: $adapter, logger: $logger);
        $subscriber->onWorkerStarted();
        $subscriber->onWorkerRunning(new WorkerRunningEvent(worker: $worker, isIdle: false));
    }
}
