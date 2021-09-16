<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Event\WorkerEventInterface;
use SchedulerBundle\Event\WorkerSleepingEvent;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use function function_exists;
use function pcntl_signal;
use function sprintf;
use const SIGHUP;
use const SIGINT;
use const SIGQUIT;
use const SIGTERM;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnSignalSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function onTaskExecuting(TaskExecutingEvent $taskExecutingEvent): void
    {
        foreach ([SIGTERM, SIGINT] as $signal) {
            pcntl_signal($signal, function () use ($signal, $taskExecutingEvent): void {
                $task = $taskExecutingEvent->getTask();
                $worker = $taskExecutingEvent->getWorker();

                $worker->stop();

                $task->setState(TaskInterface::CANCELLED);
                $task->setExecutionState(TaskInterface::TO_RETRY);

                $this->logger->warning(sprintf('The currently running worker has been stopped due to the signal "%d", the task has been marked as "TO_RETRY"', $signal));
            });
        }
    }

    public function onWorkerStarted(WorkerStartedEvent $workerStartedEvent): void
    {
        $this->stopWorker($workerStartedEvent);
    }

    public function onWorkerRunning(WorkerRunningEvent $workerRunningEvent): void
    {
        pcntl_signal(SIGHUP, function () use ($workerRunningEvent): void {
            $workerRunningEvent->getWorker()->restart();

            $this->logger->warning('The currently running worker has been stopped due to the signal SIGHUP');
        });
    }

    public function onWorkerSleeping(WorkerSleepingEvent $workerSleepingEvent): void
    {
        $this->stopWorker($workerSleepingEvent);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        if (!function_exists('pcntl_signal')) {
            return [];
        }

        return [
            TaskExecutingEvent::class => ['onTaskExecuting', 100],
            WorkerStartedEvent::class => ['onWorkerStarted', 100],
            WorkerRunningEvent::class => ['onWorkerRunning', 100],
            WorkerSleepingEvent::class => ['onWorkerSleeping', 100],
        ];
    }

    private function stopWorker(WorkerEventInterface $event): void
    {
        foreach ([SIGTERM, SIGINT, SIGQUIT, SIGHUP] as $signal) {
            pcntl_signal($signal, function () use ($event, $signal): void {
                $event->getWorker()->stop();

                $this->logger->warning(sprintf('The worker has been stopped due to the signal "%d"', $signal));
            });
        }
    }
}
