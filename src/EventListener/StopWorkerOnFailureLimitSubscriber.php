<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnFailureLimitSubscriber implements EventSubscriberInterface
{
    private $logger;
    private $maximumFailedTasks;
    private $failedTasks;

    public function __construct(int $maximumFailedTasks, LoggerInterface $logger = null)
    {
        $this->maximumFailedTasks = $maximumFailedTasks;
        $this->logger = $logger ?: new NullLogger();
    }

    public function onTaskFailedEvent(): void
    {
        ++$this->failedTasks;
    }

    public function onWorkerStarted(WorkerRunningEvent $event): void
    {
        $worker = $event->getWorker();

        if ($event->isIdle() && $this->failedTasks >= $this->maximumFailedTasks) {
            $this->failedTasks = 0;
            $worker->stop();
            $this->logger->info(sprintf('Worker has stopped due to the failure limit of %d exceeded', $this->maximumFailedTasks));
        }
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskFailedEvent::class => 'onTaskFailedEvent',
            WorkerRunningEvent::class => 'onWorkerStarted',
        ];
    }
}
