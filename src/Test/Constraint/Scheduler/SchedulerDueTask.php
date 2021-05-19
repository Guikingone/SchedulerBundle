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
        return sprintf('%s %s due', $this->expectedCount, $this->expectedCount > 1 ? 'are' : 'is');
    }

    /**
     * @param mixed|SchedulerInterface $scheduler
     */
    protected function matches($scheduler): bool
    {
        return $this->expectedCount === $scheduler->getDueTasks()->count();
    }
}
