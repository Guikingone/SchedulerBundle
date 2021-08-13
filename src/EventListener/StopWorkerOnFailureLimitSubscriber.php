<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Exception\InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnFailureLimitSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;
    private int $maximumFailedTasks;
    private int $failedTasks = 0;

    public function __construct(
        int $maximumFailedTasks,
        ?LoggerInterface $logger = null
    ) {
        $this->maximumFailedTasks = $maximumFailedTasks;
        $this->logger = $logger ?? new NullLogger();

        if ($maximumFailedTasks <= 0) {
            throw new InvalidArgumentException(sprintf('The failure limit must be greater than 0, given %d', $maximumFailedTasks));
        }
    }

    public function onTaskFailedEvent(): void
    {
        ++$this->failedTasks;
    }

    public function onWorkerStarted(WorkerRunningEvent $workerRunningEvent): void
    {
        $worker = $workerRunningEvent->getWorker();

        if ($workerRunningEvent->isIdle() && $this->failedTasks >= $this->maximumFailedTasks) {
            $this->failedTasks = 0;
            $worker->stop();

            $this->logger->info(sprintf(
                'Worker has stopped due to the failure limit of %d exceeded',
                $this->maximumFailedTasks
            ));
            $this->logger->info(sprintf(
                'Failure limit back to: %d',
                $this->failedTasks
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskFailedEvent::class => 'onTaskFailedEvent',
            WorkerRunningEvent::class => 'onWorkerStarted',
        ];
    }
}
