<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint\Scheduler;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\SchedulerInterface;

use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerDueTask extends Constraint
{
    public function __construct(private int $expectedCount)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('%s %s due', $this->expectedCount, $this->expectedCount > 1 ? 'are' : 'is');
    }

    /**
     * @param mixed|SchedulerInterface $other
     */
    protected function matches($other): bool
    {
        return $this->expectedCount === $other->getDueTasks()->count();
    }
}
