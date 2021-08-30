<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function onTaskScheduled(TaskScheduledEvent $taskScheduledEvent): void
    {
        $this->logger->info('A task has been scheduled', [
            'task' => $taskScheduledEvent->getTask()->getName(),
        ]);
    }

    public function onTaskUnscheduled(TaskUnscheduledEvent $taskUnscheduledEvent): void
    {
        $this->logger->info('A task has been unscheduled', [
            'task' => $taskUnscheduledEvent->getTask(),
        ]);
    }

    public function onTaskExecuted(TaskExecutedEvent $taskExecutedEvent): void
    {
        $this->logger->info('A task has been executed', [
            'task' => $taskExecutedEvent->getTask()->getName(),
        ]);
    }

    public function onTaskFailed(TaskFailedEvent $taskFailedEvent): void
    {
        $this->logger->error('A task execution has failed', [
            'task' => $taskFailedEvent->getTask()->getTask()->getName(),
        ]);
    }

    /**
     * {@inheritdoc}
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
