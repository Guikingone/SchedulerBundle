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
    public function __construct(private int $expectedCount)
    {
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
        if (!$other instanceof ProbeInterface) {
            return false;
        }

        return $this->expectedCount === $other->getFailedTasks();
    }
}
