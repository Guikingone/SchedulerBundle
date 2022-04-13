<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker\ExecutionPolicy;

use PHPUnit\Framework\TestCase;
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
}
