<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use Countable;
use SchedulerBundle\Task\ProbeTask;
use function array_filter;
use function count;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskEventList implements Countable
{
    /**
     * @var TaskEventInterface[]
     */
    private array $events = [];

    public function addEvent(TaskEventInterface $taskEvent): void
    {
        $this->events[] = $taskEvent;
    }

    /**
     * @return TaskEventInterface[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @return array<int, TaskScheduledEvent>
     */
    public function getScheduledTaskEvents(): array
    {
        return array_filter($this->events, static fn (TaskEventInterface $event): bool => $event instanceof TaskScheduledEvent);
    }

    /**
     * @return array<int, TaskUnscheduledEvent>
     */
    public function getUnscheduledTaskEvents(): array
    {
        return array_filter($this->events, static fn (TaskEventInterface $event): bool => $event instanceof TaskUnscheduledEvent);
    }

    /**
     * @return array<int, TaskExecutedEvent>
     */
    public function getExecutedTaskEvents(): array
    {
        return array_filter($this->events, static fn (TaskEventInterface $event): bool => $event instanceof TaskExecutedEvent);
    }

    /**
     * @return array<int, TaskFailedEvent>
     */
    public function getFailedTaskEvents(): array
    {
        return array_filter($this->events, static fn (TaskEventInterface $event): bool => $event instanceof TaskFailedEvent);
    }

    /**
     * @return array<int, TaskScheduledEvent>
     */
    public function getQueuedTaskEvents(): array
    {
        return array_filter($this->events, static fn (TaskEventInterface $event): bool => $event instanceof TaskScheduledEvent && $event->getTask()->isQueued());
    }

    /**
     * @return array<int, TaskScheduledEvent>
     */
    public function getProbeTaskEvents(): array
    {
        return array_filter($this->events, static fn (TaskEventInterface $event): bool => $event instanceof TaskScheduledEvent && $event->getTask() instanceof ProbeTask);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->events);
    }
}
