<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Probe\ProbeInterface;

use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeExecutedTask extends Constraint
{
    public function __construct(private int $expectedCount)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('has found %s executed task%s', $this->expectedCount, 1 < $this->expectedCount ? 's' : '');
    }

    /**
     * @param mixed|ProbeInterface $other
     */
    protected function matches($other): bool
    {
        return $this->expectedCount === $other->getExecutedTasks();
    }
}
