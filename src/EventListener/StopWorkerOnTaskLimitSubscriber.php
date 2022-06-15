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

    public function __construct(private readonly int $maximumTasks, private readonly LoggerInterface $logger = new NullLogger())
    {
    }

    public function onWorkerRunning(WorkerRunningEvent $workerRunningEvent): void
    {
        if (!$workerRunningEvent->isIdle() && ++$this->consumedTasks >= $this->maximumTasks) {
            $worker = $workerRunningEvent->getWorker();

            $worker->stop();

            $this->logger->info(message: 'The worker has been stopped due to maximum tasks executed', context: [
                'count' => $this->consumedTasks,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
