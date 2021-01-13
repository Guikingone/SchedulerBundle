<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\MemoryUsagePolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MemoryUsagePolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new MemoryUsagePolicy();

        self::assertFalse($policy->support('test'));
        self::assertTrue($policy->support('memory_usage'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getExecutionMemoryUsage')->willReturn(10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getExecutionMemoryUsage')->willReturn(15);

        $policy = new MemoryUsagePolicy();

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $policy->sort(['foo' => $secondTask, 'app' => $task]));
    }
}
