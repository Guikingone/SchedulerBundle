<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\RoundRobinPolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new RoundRobinPolicy();

        self::assertFalse($policy->support('test'));
        self::assertTrue($policy->support('round_robin'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionComputationTime')->willReturn(12.0);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getExecutionComputationTime')->willReturn(10.0);
        $secondTask->expects(self::once())->method('getMaxDuration')->willReturn(10.0);

        $policy = new RoundRobinPolicy();

        self::assertSame(['bar' => $task, 'foo' => $secondTask], $policy->sort(['foo' => $secondTask, 'bar' => $task]));
    }
}
