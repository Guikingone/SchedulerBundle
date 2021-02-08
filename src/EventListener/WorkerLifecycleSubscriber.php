<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerLifecycleSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
    }

    public function onWorkerRestarted(WorkerRestartedEvent $event): void
    {
        $worker = $event->getWorker();

        $this->logger->info('The worker has been restarted', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $worker->getLastExecutedTask()->getName(),
        ]);
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $worker = $event->getWorker();

        $this->logger->info('The worker is currently running', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $worker->getLastExecutedTask()->getName(),
            'idle' => $event->isIdle(),
        ]);
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $worker = $event->getWorker();

        $this->logger->info('The worker has been started', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $worker->getLastExecutedTask()->getName(),
        ]);
    }

    public function onWorkerStopped(WorkerStoppedEvent $event): void
    {
        $worker = $event->getWorker();

        $this->logger->info('The worker has been stopped', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $worker->getLastExecutedTask()->getName(),
        ]);
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerRestartedEvent::class => 'onWorkerRestarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerStoppedEvent::class => 'onWorkerStopped',
        ];
    }
}
