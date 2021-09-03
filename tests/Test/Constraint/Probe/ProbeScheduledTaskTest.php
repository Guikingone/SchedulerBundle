<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Probe\ProbeInterface;
use SchedulerBundle\Test\Constraint\Probe\ProbeScheduledTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeScheduledTaskTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getScheduledTasks')->willReturn(10);

        $constraint = new ProbeScheduledTask(1);
        self::assertSame('has found 1 scheduled task', $constraint->toString());

        self::assertFalse($constraint->evaluate($probe, '', true));
    }

    public function testConstraintCannotMatchWithException(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getScheduledTasks')->willReturn(10);

        $constraint = new ProbeScheduledTask(1);
        self::assertSame('has found 1 scheduled task', $constraint->toString());

        self::expectException(ExpectationFailedException::class);
        self::expectExceptionMessage('has found 1 scheduled task');
        self::expectExceptionCode(0);
        $constraint->evaluate($probe);
    }

    public function testConstraintCannotMatchMultipleTasksWithException(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getScheduledTasks')->willReturn(1);

        $constraint = new ProbeScheduledTask(10);
        self::assertSame('has found 10 scheduled tasks', $constraint->toString());

        self::expectException(ExpectationFailedException::class);
        self::expectExceptionMessage('has found 10 scheduled tasks');
        self::expectExceptionCode(0);
        $constraint->evaluate($probe);
    }

    public function testConstraintCanMatch(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getScheduledTasks')->willReturn(1);

        $constraint = new ProbeScheduledTask(1);

        self::assertTrue($constraint->evaluate($probe, '', true));
    }
}
