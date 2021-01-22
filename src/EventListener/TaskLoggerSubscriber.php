<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SchedulerBundle\Event\TaskEventInterface;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLoggerSubscriber implements EventSubscriberInterface
{
    /**
     * @var TaskEventList
     */
    private $events;

    public function __construct()
    {
        $this->events = new TaskEventList();
    }

    public function onTask(TaskEventInterface $taskEvent): void
    {
        $this->events->addEvent($taskEvent);
    }

    public function getEvents(): TaskEventList
    {
        return $this->events;
    }

    /**
     * @return array<string, array<int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskExecutedEvent::class => ['onTask', -255],
            TaskFailedEvent::class => ['onTask', -255],
            TaskScheduledEvent::class => ['onTask', -255],
            TaskUnscheduledEvent::class => ['onTask', -255],
        ];
    }
}
