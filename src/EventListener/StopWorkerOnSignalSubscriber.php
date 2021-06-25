<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use SchedulerBundle\Event\WorkerEventInterface;
use SchedulerBundle\Event\WorkerSleepingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use function function_exists;
use function pcntl_signal;
use const SIGHUP;
use const SIGINT;
use const SIGQUIT;
use const SIGTERM;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnSignalSubscriber implements EventSubscriberInterface
{
    public function onWorkerStarted(WorkerStartedEvent $workerStartedEvent): void
    {
        $this->stopWorker($workerStartedEvent);
    }

    public function onWorkerRunning(WorkerRunningEvent $workerRunningEvent): void
    {
        pcntl_signal(SIGHUP, static function () use ($workerRunningEvent): void {
            $workerRunningEvent->getWorker()->restart();
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
            WorkerStartedEvent::class => ['onWorkerStarted', 100],
            WorkerRunningEvent::class => ['onWorkerRunning', 100],
            WorkerSleepingEvent::class => ['onWorkerSleeping', 100],
        ];
    }

    private function stopWorker(WorkerEventInterface $event): void
    {
        foreach ([SIGTERM, SIGINT, SIGQUIT] as $signal) {
            pcntl_signal($signal, static function () use ($event): void {
                $event->getWorker()->stop();
            });
        }
    }
}
