<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskEventList implements \Countable
{
    /**
     * @var TaskEventInterface[]
     */
    private $events = [];

    public function addEvent(TaskEventInterface $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return TaskEventInterface[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @return array<int,TaskScheduledEvent>
     */
    public function getScheduledTaskEvents(): array
    {
        return array_filter($this->events, function (TaskEventInterface $event): bool {
            return $event instanceof TaskScheduledEvent;
        });
    }

    /**
     * @return array<int,TaskUnscheduledEvent>
     */
    public function getUnscheduledTaskEvents(): array
    {
        return array_filter($this->events, function (TaskEventInterface $event): bool {
            return $event instanceof TaskUnscheduledEvent;
        });
    }

    /**
     * @return array<int,TaskExecutedEvent>
     */
    public function getExecutedTaskEvents(): array
    {
        return array_filter($this->events, function (TaskEventInterface $event): bool {
            return $event instanceof TaskExecutedEvent;
        });
    }

    /**
     * @return array<int,TaskFailedEvent>
     */
    public function getFailedTaskEvents(): array
    {
        return array_filter($this->events, function (TaskEventInterface $event): bool {
            return $event instanceof TaskFailedEvent;
        });
    }

    /**
     * @return array<int,TaskScheduledEvent>
     */
    public function getQueuedTaskEvents(): array
    {
        return array_filter($this->events, function (TaskEventInterface $event): bool {
            return $event instanceof TaskScheduledEvent && $event->getTask()->isQueued();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return \count($this->events);
    }
}
