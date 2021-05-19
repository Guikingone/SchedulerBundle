<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint\Probe;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Test\Constraint\Probe\ProbeEnabled;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeEnabledTest extends TestCase
{
    public function testConstraintMatch(): void
    {
        $constraint = new ProbeEnabled(true);

        self::assertFalse($constraint->evaluate(false, '', true));
        self::assertTrue($constraint->evaluate(true, '', true));

        self::expectException(ExpectationFailedException::class);
        self::expectExceptionMessage('match the current probe state, current state: enabled');
        self::expectExceptionCode(0);
        $constraint->evaluate(false);
    }
}
