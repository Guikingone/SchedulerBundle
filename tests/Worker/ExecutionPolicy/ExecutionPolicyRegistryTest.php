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
        $registry = new ExecutionPolicyRegistry(policies: []);

        self::assertCount(expectedCount: 0, haystack: $registry);
    }

    public function testRegistryCannotReturnInvalidPolicy(): void
    {
        $registry = new ExecutionPolicyRegistry(policies: [
            new DefaultPolicy(),
        ]);
        self::assertCount(expectedCount: 1, haystack: $registry);

        self::expectException(exception: InvalidArgumentException::class);
        self::expectExceptionMessage(message: 'No policy found for "foo"');
        self::expectExceptionCode(code: 0);
        $registry->find(policy: 'foo');
    }

    public function testRegistryCannotReturnMultiplePolicies(): void
    {
        $registry = new ExecutionPolicyRegistry(policies: [
            new DefaultPolicy(),
            new DefaultPolicy(),
        ]);
        self::assertCount(expectedCount: 2, haystack: $registry);

        self::expectException(exception: InvalidArgumentException::class);
        self::expectExceptionMessage(message: 'More than one policy found, consider improving the policy(es)');
        self::expectExceptionCode(code: 0);
        $registry->find(policy: 'default');
    }

    public function testRegistryCanReturnPolicy(): void
    {
        $registry = new ExecutionPolicyRegistry(policies: [
            new DefaultPolicy(),
        ]);
        self::assertCount(expectedCount: 1, haystack: $registry);

        $policy = $registry->find(policy: 'default');
        self::assertInstanceOf(expected: DefaultPolicy::class, actual: $policy);
    }
}
