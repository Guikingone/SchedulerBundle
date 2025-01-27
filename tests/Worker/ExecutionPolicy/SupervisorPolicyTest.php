<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker\ExecutionPolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Worker\ExecutionPolicy\SupervisorPolicy;
use SchedulerBundle\Worker\Supervisor\WorkerSupervisorInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SupervisorPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $supervisor = $this->createMock(originalClassName: WorkerSupervisorInterface::class);

        $policy = new SupervisorPolicy(supervisor: $supervisor);

        self::assertTrue(condition: $policy->support(policy: 'supervisor'));
        self::assertFalse(condition: $policy->support(policy: 'foo'));
    }
}
