<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
 *
 * @experimental in 5.3
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
     * @return array<string,array<int,string|int>>
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
