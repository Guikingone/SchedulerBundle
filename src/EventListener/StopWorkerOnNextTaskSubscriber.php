<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerSleepingEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function microtime;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnNextTaskSubscriber implements EventSubscriberInterface
{
    public const STOP_NEXT_TASK_TIMESTAMP_KEY = 'worker.stop_next_task.timestamp';

    private ?float $workerStartTimestamp = null;
    private LoggerInterface $logger;

    public function __construct(
        private CacheItemPoolInterface $stopWorkerCacheItemPool,
        LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function onWorkerStarted(): void
    {
        $this->workerStartTimestamp = microtime(as_float: true);
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($event->isIdle()) {
            return;
        }

        $this->doStopWorker($event);

        $this->logger->info(message: 'Worker will stop once the next task is executed');
    }

    public function onWorkerSleeping(WorkerSleepingEvent $event): void
    {
        if ($event->getSleepDuration() > 0) {
            return;
        }

        $this->doStopWorker($event);

        $this->logger->info(message: 'Worker will stop once the next task is executed');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
            WorkerSleepingEvent::class => 'onWorkerSleeping',
        ];
    }

    private function doStopWorker(WorkerRunningEvent|WorkerSleepingEvent $event): void
    {
        if (!$this->shouldStop()) {
            return;
        }

        $worker = $event->getWorker();
        $worker->stop();
    }

    private function shouldStop(): bool
    {
        if (!$this->stopWorkerCacheItemPool->hasItem(key: self::STOP_NEXT_TASK_TIMESTAMP_KEY)) {
            return false;
        }

        $cacheItem = $this->stopWorkerCacheItemPool->getItem(key: self::STOP_NEXT_TASK_TIMESTAMP_KEY);
        if (!$cacheItem->isHit()) {
            return false;
        }

        return $this->workerStartTimestamp < $cacheItem->get();
    }
}
