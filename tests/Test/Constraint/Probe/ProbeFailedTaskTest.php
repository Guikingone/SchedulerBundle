<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Probe\ProbeInterface;
use SchedulerBundle\Test\Constraint\Probe\ProbeFailedTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeFailedTaskTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getFailedTasks')->willReturn(10);

        $constraint = new ProbeFailedTask(1);

        self::assertFalse($constraint->evaluate($probe, '', true));
    }

    public function testConstraintCannotMatchWithException(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getFailedTasks')->willReturn(10);

        $constraint = new ProbeFailedTask(1);

        self::expectException(ExpectationFailedException::class);
        self::expectExceptionMessage('has found 1 failed task');
        self::expectExceptionCode(0);
        $constraint->evaluate($probe);
    }

    public function testConstraintCanMatch(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getFailedTasks')->willReturn(1);

        $constraint = new ProbeFailedTask(1);

        self::assertTrue($constraint->evaluate($probe, '', true));
    }
}
