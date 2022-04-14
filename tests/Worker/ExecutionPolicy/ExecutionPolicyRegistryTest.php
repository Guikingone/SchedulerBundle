<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker\ExecutionPolicy;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Worker\ExecutionPolicy\DefaultPolicy;
use SchedulerBundle\Worker\ExecutionPolicy\ExecutionPolicyRegistry;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecutionPolicyRegistryTest extends TestCase
{
    public function testRegistryCanCount(): void
    {
        $registry = new ExecutionPolicyRegistry([]);

        self::assertCount(0, $registry);
    }

    public function testRegistryCannotReturnInvalidPolicy(): void
    {
        $registry = new ExecutionPolicyRegistry([
            new DefaultPolicy(),
        ]);
        self::assertCount(1, $registry);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('No policy found for "foo"');
        self::expectExceptionCode(0);
        $registry->find('foo');
    }

    public function testRegistryCannotReturnMultiplePolicies(): void
    {
        $registry = new ExecutionPolicyRegistry([
            new DefaultPolicy(),
            new DefaultPolicy(),
        ]);
        self::assertCount(2, $registry);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('More than one policy found, consider improving the policy(es)');
        self::expectExceptionCode(0);
        $registry->find('default');
    }

    public function testRegistryCanReturnPolicy(): void
    {
        $registry = new ExecutionPolicyRegistry([
            new DefaultPolicy(),
        ]);
        self::assertCount(1, $registry);

        $policy = $registry->find('default');
        self::assertInstanceOf(DefaultPolicy::class, $policy);
    }
}
