<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\Constraint\Constraint;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeEnabled extends Constraint
{
    private bool $expectedState;

    public function __construct(bool $expectedState)
    {
        $this->expectedState = $expectedState;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('match the current probe state, current state: %s', $this->expectedState ? 'enabled' : 'disabled');
    }

    /**
     * @param mixed|bool $probeState
     */
    protected function matches($probeState): bool
    {
        return $this->expectedState === $probeState;
    }
}
