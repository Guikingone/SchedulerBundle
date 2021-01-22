<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Task\TaskInterface;
use function microtime;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnTimeLimitSubscriber implements EventSubscriberInterface
{
    /**
     * @var float|null
     */
    private $endTime;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $timeLimitInSeconds;

    public function __construct(int $timeLimitInSeconds, LoggerInterface $logger = null)
    {
        $this->timeLimitInSeconds = $timeLimitInSeconds;
        $this->logger = $logger ?: new NullLogger();
    }

    public function onWorkerStarted(): void
    {
        $this->endTime = microtime(true) + $this->timeLimitInSeconds;
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->endTime < microtime(true)) {
            $worker = $event->getWorker();
            $worker->stop();

            $this->logger->info(sprintf('Worker stopped due to time limit of %d seconds exceeded', $this->timeLimitInSeconds), [
                'lastExecutedTask' => $worker->getLastExecutedTask() instanceof TaskInterface ? $worker->getLastExecutedTask()->getName() : null,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
