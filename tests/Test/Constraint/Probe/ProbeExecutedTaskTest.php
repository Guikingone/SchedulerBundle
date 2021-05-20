<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Probe\ProbeInterface;
use SchedulerBundle\Test\Constraint\Probe\ProbeExecutedTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeExecutedTaskTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getExecutedTasks')->willReturn(10);

        $constraint = new ProbeExecutedTask(1);

        self::assertFalse($constraint->evaluate($probe, '', true));
    }

    public function testConstraintCannotMatchWithException(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getExecutedTasks')->willReturn(10);

        $constraint = new ProbeExecutedTask(1);

        self::expectException(ExpectationFailedException::class);
        self::expectExceptionMessage('has found 1 executed task');
        self::expectExceptionCode(0);
        $constraint->evaluate($probe);
    }

    public function testConstraintCanMatch(): void
    {
        $probe = $this->createMock(ProbeInterface::class);
        $probe->expects(self::once())->method('getExecutedTasks')->willReturn(1);

        $constraint = new ProbeExecutedTask(1);

        self::assertTrue($constraint->evaluate($probe, '', true));
    }
}
