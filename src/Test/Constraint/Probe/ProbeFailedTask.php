<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Probe\ProbeInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeFailedTask extends Constraint
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
        return sprintf('has found %s failed task%s', $this->expectedCount, 1 < $this->expectedCount ? 's' : '');
    }

    /**
     * @param mixed|ProbeInterface $other
     */
    protected function matches($other): bool
    {
        return $this->expectedCount === $other->getFailedTasks();
    }
}
