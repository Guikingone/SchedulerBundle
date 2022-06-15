<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Event\TaskEventList;
use function count;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUnscheduled extends Constraint
{
    public function __construct(private readonly int $expectedCount)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('%s %s been unscheduled', $this->expectedCount, $this->expectedCount > 1 ? 'have' : 'has');
    }

    /**
     * @param mixed|TaskEventList $other
     */
    protected function matches($other): bool
    {
        return $this->expectedCount === (is_countable($other->getUnscheduledTaskEvents()) ? count($other->getUnscheduledTaskEvents()) : 0);
    }
}
