<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\WorkerRunningEvent;
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

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $this->workerStartTimestamp = microtime(as_float: true);

        if (!$this->shouldStop()) {
            return;
        }

        $worker = $event->getWorker();
        $worker->stop();

        $this->stopWorkerCacheItemPool->deleteItem(key: self::STOP_NEXT_TASK_TIMESTAMP_KEY);
        $this->logger->info(message: 'The worker will be stopped');
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $worker = $event->getWorker();
        $workerConfiguration = $worker->getConfiguration();

        if ($event->isIdle() || $workerConfiguration->shouldStop()) {
            return;
        }

        if (!$this->shouldStop()) {
            return;
        }

        $worker->stop();

        $this->logger->info(message: 'The worker will stop once the next task is executed');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
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

        return ($this->workerStartTimestamp > $cacheItem->get() || $this->workerStartTimestamp < $cacheItem->get());
    }
}
