<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Task\TaskInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecuted extends Constraint
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
        return sprintf('%s %s been executed', $this->expectedCount, $this->expectedCount > 1 ? 'have' : 'has');
    }

    /**
     * @param mixed|TaskEventList $other
     */
    protected function matches($other): bool
    {
        return $this->expectedCount === $this->countExecutedTasks($other);
    }

    private function countExecutedTasks(TaskEventList $taskEventList): int
    {
        $count = 0;
        foreach ($taskEventList->getEvents() as $taskEvent) {
            if (!$taskEvent instanceof TaskExecutedEvent) {
                continue;
            }

            if (TaskInterface::SUCCEED !== $taskEvent->getTask()->getExecutionState()) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
