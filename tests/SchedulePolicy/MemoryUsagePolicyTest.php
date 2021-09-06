<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\MemoryUsagePolicy;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;

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
        $task = new NullTask('app', [
            'execution_memory_usage' => 10,
        ]);

        $secondTask = new NullTask('foo', [
            'execution_memory_usage' => 15,
        ]);

        $memoryUsagePolicy = new MemoryUsagePolicy();
        $sortedTasks = $memoryUsagePolicy->sort(new TaskList([$task, $secondTask]));

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }
}
