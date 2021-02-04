<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskScheduledEvent;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskQueued extends Constraint
{
    private int $expectedCount;

    public function __construct(int $expectedCount)
    {
        $this->expectedCount = $expectedCount;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('%s %s been queued', $this->expectedCount, $this->expectedCount > 1 ? 'have' : 'has');
    }

    /**
     * @param TaskEventList $eventsList
     *
     * {@inheritdoc}
     */
    protected function matches($eventsList): bool
    {
        return $this->expectedCount === $this->countQueuedTasks($eventsList);
    }

    private function countQueuedTasks(TaskEventList $eventsList): int
    {
        $count = 0;
        foreach ($eventsList->getEvents() as $event) {
            if (!$event instanceof TaskScheduledEvent || !$event->getTask()->isQueued()) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
