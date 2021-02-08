<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SchedulerBundle\Event\WorkerRunningEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnTaskLimitSubscriber implements EventSubscriberInterface
{
    private int $consumedTasks = 0;

    private int $maximumTasks;

    private LoggerInterface $logger;

    public function __construct(int $maximumTasks, LoggerInterface $logger = null)
    {
        $this->maximumTasks = $maximumTasks;
        $this->logger = $logger ?: new NullLogger();
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$event->isIdle() && ++$this->consumedTasks >= $this->maximumTasks) {
            $event->getWorker()->stop();

            $this->logger->info('The worker has been stopped due to maximum tasks executed', [
                'count' => $this->consumedTasks,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
