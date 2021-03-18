<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Probe\Probe;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeSubscriber implements EventSubscriberInterface
{
    private Probe $probe;

    public function __construct(Probe $probe)
    {
        $this->probe = $probe;
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $this->probe->addExecutedTask($event->getTask());
    }

    public function onTaskFailed(TaskFailedEvent $event): void
    {
        $this->probe->addFailedTask($event->getTask());
    }

    public function onTaskScheduled(TaskScheduledEvent $event): void
    {
        $this->probe->addScheduledTask($event->getTask());
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskExecutedEvent::class => 'onTaskExecuted',
            TaskFailedEvent::class => 'onTaskFailed',
            TaskScheduledEvent::class => 'onTaskScheduled',
        ];
    }
}
