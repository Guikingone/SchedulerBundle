<?php

declare(strict_types=1);

namespace SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\Constraint\Constraint;
use SchedulerBundle\Probe\ProbeInterface;
use function json_encode;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeState extends Constraint
{
    /**
     * @var array<string, int>
     */
    private array $expectedState;

    /**
     * @param array<string, int> $expectedState
     */
    public function __construct(array $expectedState)
    {
        $this->expectedState = $expectedState;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('match current probe state: %s', json_encode($this->expectedState, JSON_THROW_ON_ERROR));
    }

    /**
     * @param mixed|ProbeInterface $probe
     */
    protected function matches($probe): bool
    {
        return $this->expectedState === [
            'executedTasks' => $probe->getExecutedTasks(),
            'failedTasks' => $probe->getFailedTasks(),
            'scheduledTasks' => $probe->getScheduledTasks(),
        ];
    }
}
