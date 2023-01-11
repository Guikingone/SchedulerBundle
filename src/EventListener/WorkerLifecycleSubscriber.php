<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\WorkerForkedEvent;
use SchedulerBundle\Event\WorkerPausedEvent;
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

    public function onWorkerForked(WorkerForkedEvent $workerForkedEvent): void
    {
        $forkedWorker = $workerForkedEvent->getForkedWorker();
        $forkedConfiguration = $forkedWorker->getConfiguration();

        $newWorker = $workerForkedEvent->getNewWorker();
        $configuration = $newWorker->getConfiguration();

        $this->logger->info('The worker has been forked', [
            'forkedWorker' => $forkedConfiguration->toArray(),
            'newWorker' => $configuration->toArray(),
        ]);
    }

    public function onWorkerPaused(WorkerPausedEvent $workerPausedEvent): void
    {
        $worker = $workerPausedEvent->getWorker();
        $configuration = $worker->getConfiguration();

        $this->logger->info('The worker has been paused', [
            'options' => $configuration->toArray(),
        ]);
    }

    public function onWorkerRestarted(WorkerRestartedEvent $workerRestartedEvent): void
    {
        $worker = $workerRestartedEvent->getWorker();
        $lastExecutedTask = $worker->getLastExecutedTask();

        $this->logger->info('The worker has been restarted', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $lastExecutedTask instanceof TaskInterface ? $lastExecutedTask->getName() : null,
        ]);
    }

    public function onWorkerRunning(WorkerRunningEvent $workerRunningEvent): void
    {
        $worker = $workerRunningEvent->getWorker();
        $lastExecutedTask = $worker->getLastExecutedTask();

        $this->logger->info('The worker is currently running', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $lastExecutedTask instanceof TaskInterface ? $lastExecutedTask->getName() : null,
            'idle' => $workerRunningEvent->isIdle(),
        ]);
    }

    public function onWorkerStarted(WorkerStartedEvent $workerStartedEvent): void
    {
        $worker = $workerStartedEvent->getWorker();
        $lastExecutedTask = $worker->getLastExecutedTask();

        $this->logger->info('The worker has been started', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $lastExecutedTask instanceof TaskInterface ? $lastExecutedTask->getName() : null,
        ]);
    }

    public function onWorkerStopped(WorkerStoppedEvent $workerStoppedEvent): void
    {
        $worker = $workerStoppedEvent->getWorker();
        $lastExecutedTask = $worker->getLastExecutedTask();

        $this->logger->info('The worker has been stopped', [
            'failedTasks' => $worker->getFailedTasks()->count(),
            'lastExecutedTask' => $lastExecutedTask instanceof TaskInterface ? $lastExecutedTask->getName() : null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerForkedEvent::class => 'onWorkerForked',
            WorkerPausedEvent::class => 'onWorkerPaused',
            WorkerRestartedEvent::class => 'onWorkerRestarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerStoppedEvent::class => 'onWorkerStopped',
        ];
    }
}
