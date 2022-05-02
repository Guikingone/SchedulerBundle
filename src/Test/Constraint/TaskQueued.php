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
    public function __construct(private int $expectedCount)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('contains %s task%s that %s been queued', $this->expectedCount, $this->expectedCount > 1 ? 's' : '', $this->expectedCount > 1 ? 'have' : 'has');
    }

    /**
     * @param mixed|TaskEventList $other
     */
    protected function matches($other): bool
    {
        if (!$other instanceof TaskEventList) {
            return false;
        }

        return $this->expectedCount === $this->countQueuedTasks($other);
    }

    private function countQueuedTasks(TaskEventList $taskEventList): int
    {
        $count = 0;
        foreach ($taskEventList->getEvents() as $taskEvent) {
            if (!$taskEvent instanceof TaskScheduledEvent) {
                continue;
            }

            if (!$taskEvent->getTask()->isQueued()) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
