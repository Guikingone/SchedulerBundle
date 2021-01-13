<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\ExecutionDurationPolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecutionDurationPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new ExecutionDurationPolicy();

        self::assertFalse($policy->support('test'));
        self::assertTrue($policy->support('execution_duration'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getExecutionComputationTime')->willReturn(10.0);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getExecutionComputationTime')->willReturn(12.0);

        $policy = new ExecutionDurationPolicy();

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $policy->sort(['foo' => $secondTask, 'app' => $task]));
    }
}
