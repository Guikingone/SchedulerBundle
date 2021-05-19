<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Probe\ProbeInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeExecutedTask extends Constraint
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
        // TODO: Implement toString() method.
    }

    /**
     * @param mixed|ProbeInterface $probe
     */
    protected function matches($probe): bool
    {
        return $this->expectedCount === $probe->getExecutedTasks();
    }
}
