<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use function microtime;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Task\TaskInterface;

use function sprintf;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnTimeLimitSubscriber implements EventSubscriberInterface
{
    private ?float $endTime = null;
    private LoggerInterface $logger;

    public function __construct(
        private int $timeLimitInSeconds,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function onWorkerStarted(): void
    {
        $this->endTime = microtime(true) + $this->timeLimitInSeconds;
    }

    public function onWorkerRunning(WorkerRunningEvent $workerRunningEvent): void
    {
        if ($this->endTime < microtime(true)) {
            $worker = $workerRunningEvent->getWorker();
            $worker->stop();

            $lastExecutedTask = $worker->getLastExecutedTask();
            $this->logger->info(sprintf('Worker stopped due to time limit of %d seconds exceeded', $this->timeLimitInSeconds), [
                'lastExecutedTask' => $lastExecutedTask instanceof TaskInterface ? $lastExecutedTask->getName() : null,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
