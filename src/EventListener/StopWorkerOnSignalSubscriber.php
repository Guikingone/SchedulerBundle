<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnSignalSubscriber implements EventSubscriberInterface
{
    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        foreach ([\SIGTERM, \SIGINT, \SIGQUIT] as $signal) {
            \pcntl_signal($signal, static function () use ($event): void {
                $event->getWorker()->stop();
            });
        }
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        \pcntl_signal(\SIGHUP, static function () use ($event): void {
            $event->getWorker()->restart();
        });
    }

    /**
     * @return array<string,array<int,string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        if (!\function_exists('pcntl_signal')) {
            return [];
        }

        return [
            WorkerStartedEvent::class => ['onWorkerStarted', 100],
            WorkerRunningEvent::class => ['onWorkerRunning', 100],
        ];
    }
}
