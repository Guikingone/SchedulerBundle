<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\TaskEventInterface;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
    }

    public function onTaskScheduled(TaskEventInterface $event): void
    {
        $this->logger->info('A task has been scheduled', [
            'task' => $event->getTask()->getName(),
        ]);
    }

    public function onTaskUnscheduled(TaskUnscheduledEvent $event): void
    {
        $this->logger->info('A task has been unscheduled', [
            'task' => $event->getTask(),
        ]);
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $this->logger->info('A task has been executed', [
            'task' => $event->getTask()->getName(),
        ]);
    }

    public function onTaskFailed(TaskFailedEvent $event): void
    {
        $this->logger->info('A task execution has failed', [
            'task' => $event->getTask()->getTask()->getName(),
        ]);
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskScheduledEvent::class => 'onTaskScheduled',
            TaskUnscheduledEvent::class => 'onTaskUnscheduled',
            TaskExecutedEvent::class => 'onTaskExecuted',
            TaskFailedEvent::class => 'onTaskFailed',
        ];
    }
}
