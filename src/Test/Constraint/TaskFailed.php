<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Event\TaskEventList;
use function count;
use function is_countable;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskFailed extends Constraint
{
    public function __construct(private readonly int $expectedCount)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('%s %s failed', $this->expectedCount, $this->expectedCount > 1 ? 'have' : 'has');
    }

    /**
     * @param mixed|TaskEventList $other
     */
    protected function matches($other): bool
    {
        return $this->expectedCount === (is_countable($other->getFailedTaskEvents()) ? count($other->getFailedTaskEvents()) : 0);
    }
}
