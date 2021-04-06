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
        $memoryUsagePolicy = new MemoryUsagePolicy();

        self::assertFalse($memoryUsagePolicy->support('test'));
        self::assertTrue($memoryUsagePolicy->support('memory_usage'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getExecutionMemoryUsage')->willReturn(10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getExecutionMemoryUsage')->willReturn(15);

        $memoryUsagePolicy = new MemoryUsagePolicy();

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $memoryUsagePolicy->sort(['foo' => $secondTask, 'app' => $task]));
    }
}
