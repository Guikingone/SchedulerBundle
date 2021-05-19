<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Probe\ProbeInterface;
use SchedulerBundle\Test\Constraint\Probe\ProbeState;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeStateTest extends TestCase
{
    public function testConstraintMatch(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getExecutedTasks')->willReturn(0);
        $probe->expects(self::once())->method('getFailedTasks')->willReturn(0);
        $probe->expects(self::once())->method('getScheduledTasks')->willReturn(0);

        $secondProbe = $this->createMock(ProbeInterface::class);
        $secondProbe->expects(self::once())->method('getExecutedTasks')->willReturn(1);
        $secondProbe->expects(self::once())->method('getFailedTasks')->willReturn(1);
        $secondProbe->expects(self::once())->method('getScheduledTasks')->willReturn(1);

        $constraint = new ProbeState([
            'executedTasks' => 0,
            'failedTasks' => 0,
            'scheduledTasks' => 0,
        ]);

        self::assertTrue($constraint->evaluate($probe, '', true));

        self::expectException(ExpectationFailedException::class);
        self::expectExceptionMessage('match current probe state: {"executedTasks":0,"failedTasks":0,"scheduledTasks":0}');
        self::expectExceptionCode(0);
        $constraint->evaluate($secondProbe);
    }
}
