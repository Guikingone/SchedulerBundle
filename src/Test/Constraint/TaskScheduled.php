<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint;

use function count;

use PHPUnit\Framework\Constraint\Constraint;

use SchedulerBundle\Event\TaskEventList;

use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskScheduled extends Constraint
{
    public function __construct(private int $expectedCount)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('%s %s been scheduled', $this->expectedCount, $this->expectedCount > 1 ? 'have' : 'has');
    }

    /**
     * @param mixed|TaskEventList $other
     */
    protected function matches($other): bool
    {
        if (!$other instanceof TaskEventList) {
            return false;
        }

        return $this->expectedCount === count($other->getScheduledTaskEvents());
    }
}
