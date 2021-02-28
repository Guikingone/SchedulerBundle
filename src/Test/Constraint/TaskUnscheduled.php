<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUnscheduled extends Constraint
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
        return sprintf('%s %s been unscheduled', $this->expectedCount, $this->expectedCount > 1 ? 'have' : 'has');
    }

    /**
     * @param mixed|TaskEventList $eventsList
     */
    protected function matches($eventsList): bool
    {
        return $this->expectedCount === $this->countUnscheduledTask($eventsList);
    }

    private function countUnscheduledTask(TaskEventList $eventsList): int
    {
        $count = 0;
        foreach ($eventsList->getEvents() as $event) {
            if (!$event instanceof TaskUnscheduledEvent) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
