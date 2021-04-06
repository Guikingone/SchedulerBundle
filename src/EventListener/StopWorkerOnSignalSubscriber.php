<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

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
        foreach ([SIGTERM, SIGINT, SIGQUIT] as $signal) {
            pcntl_signal($signal, static function () use ($workerStartedEvent): void {
                $workerStartedEvent->getWorker()->stop();
            });
        }
    }

    public function onWorkerRunning(WorkerRunningEvent $workerRunningEvent): void
    {
        pcntl_signal(SIGHUP, static function () use ($workerRunningEvent): void {
            $workerRunningEvent->getWorker()->restart();
        });
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
        ];
    }
}
