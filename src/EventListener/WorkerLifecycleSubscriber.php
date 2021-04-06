<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerLifecycleSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function onWorkerRestarted(WorkerRestartedEvent $workerRestartedEvent): void
    {
        $worker = $workerRestartedEvent->getWorker();

        $this->logger->info('The worker has been restarted', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $worker->getLastExecutedTask() instanceof TaskInterface ? $worker->getLastExecutedTask()->getName() : null,
        ]);
    }

    public function onWorkerRunning(WorkerRunningEvent $workerRunningEvent): void
    {
        $worker = $workerRunningEvent->getWorker();

        $this->logger->info('The worker is currently running', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $worker->getLastExecutedTask() instanceof TaskInterface ? $worker->getLastExecutedTask()->getName() : null,
            'idle' => $workerRunningEvent->isIdle(),
        ]);
    }

    public function onWorkerStarted(WorkerStartedEvent $workerStartedEvent): void
    {
        $worker = $workerStartedEvent->getWorker();

        $this->logger->info('The worker has been started', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $worker->getLastExecutedTask() instanceof TaskInterface ? $worker->getLastExecutedTask()->getName() : null,
        ]);
    }

    public function onWorkerStopped(WorkerStoppedEvent $workerStoppedEvent): void
    {
        $worker = $workerStoppedEvent->getWorker();

        $this->logger->info('The worker has been stopped', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $worker->getLastExecutedTask() instanceof TaskInterface ? $worker->getLastExecutedTask()->getName() : null,
        ]);
    }

    /**
     * {@inheritdoc}
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
