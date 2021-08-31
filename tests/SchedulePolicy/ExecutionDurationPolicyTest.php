<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\ExecutionDurationPolicy;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecutionDurationPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $executionDurationPolicy = new ExecutionDurationPolicy();

        self::assertFalse($executionDurationPolicy->support('test'));
        self::assertTrue($executionDurationPolicy->support('execution_duration'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = new NullTask('app', [
            'execution_computation_time' => 10.0,
        ]);

        $secondTask = new NullTask('foo', [
            'execution_computation_time' => 12.0,
        ]);

        $executionDurationPolicy = new ExecutionDurationPolicy();
        $sortedTasks = $executionDurationPolicy->sort(new TaskList([$secondTask, $task]));

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }
}
