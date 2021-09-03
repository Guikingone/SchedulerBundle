<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\RoundRobinPolicy;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $roundRobinPolicy = new RoundRobinPolicy();

        self::assertFalse($roundRobinPolicy->support('test'));
        self::assertTrue($roundRobinPolicy->support('round_robin'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = new NullTask('bar', [
            'execution_computation_time' => 12.0,
        ]);

        $secondTask = new NullTask('foo', [
            'execution_computation_time' => 10.0,
            'max_duration' => 10.0,
        ]);

        $roundRobinPolicy = new RoundRobinPolicy();
        $sortedTasks = $roundRobinPolicy->sort(new TaskList([$secondTask, $task]));

        self::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }

    public function testTasksCanBeSortedUsingSameComputationTime(): void
    {
        $task = new NullTask('bar', [
            'execution_computation_time' => 12.0,
        ]);

        $secondTask = new NullTask('foo', [
            'execution_computation_time' => 12.0,
        ]);

        $roundRobinPolicy = new RoundRobinPolicy();
        $sortedTasks = $roundRobinPolicy->sort(new TaskList([$secondTask, $task]));

        self::assertSame([
            'foo' => $secondTask,
            'bar' => $task,
        ], $sortedTasks->toArray());
    }

    public function testTasksCanBeSortedUsingHigherComputationTime(): void
    {
        $task = new NullTask('bar', [
            'execution_computation_time' => 12.0,
        ]);

        $secondTask = new NullTask('foo', [
            'execution_computation_time' => 15.0,
        ]);

        $roundRobinPolicy = new RoundRobinPolicy();
        $sortedTasks = $roundRobinPolicy->sort(new TaskList([$secondTask, $task]));

        self::assertSame([
            'foo' => $secondTask,
            'bar' => $task,
        ], $sortedTasks->toArray());
    }
}
