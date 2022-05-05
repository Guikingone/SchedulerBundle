<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker\ExecutionPolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Worker\ExecutionPolicy\FiberPolicy;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new FiberPolicy();

        self::assertTrue(condition: $policy->support(policy: 'fiber'));
        self::assertFalse(condition: $policy->support(policy: 'foo'));
    }
}
