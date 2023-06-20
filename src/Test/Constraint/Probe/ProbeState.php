<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Probe\ProbeInterface;

use Throwable;
use function json_encode;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeState extends Constraint
{
    /**
     * @param array<string, int> $expectedState
     */
    public function __construct(private array $expectedState)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('match current probe state: %s', json_encode($this->expectedState, JSON_THROW_ON_ERROR));
    }

    /**
     * @param mixed|ProbeInterface $other
     * @throws Throwable {@see ProbeInterface::getScheduledTasks()}
     */
    protected function matches($other): bool
    {
        if (!$other instanceof ProbeInterface) {
            return false;
        }

        return $this->expectedState === [
            'executedTasks' => $other->getExecutedTasks(),
            'failedTasks' => $other->getFailedTasks(),
            'scheduledTasks' => $other->getScheduledTasks(),
        ];
    }
}
