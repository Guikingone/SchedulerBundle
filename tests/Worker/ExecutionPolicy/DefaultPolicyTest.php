<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker\ExecutionPolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Worker\ExecutionPolicy\DefaultPolicy;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DefaultPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new DefaultPolicy();

        self::assertTrue(condition: $policy->support(policy: 'default'));
        self::assertFalse(condition: $policy->support(policy: 'foo'));
    }
}
