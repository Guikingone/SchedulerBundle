<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\BatchPolicy;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BatchPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $batchPolicy = new BatchPolicy();

        self::assertFalse($batchPolicy->support('test'));
        self::assertTrue($batchPolicy->support('batch'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = new NullTask('app', [
            'priority' => 2,
        ]);

        $secondTask = new NullTask('foo', [
            'priority' => 2,
        ]);

        $batchPolicy = new BatchPolicy();
        $sortedTasks = $batchPolicy->sort(new TaskList([
            $secondTask,
            $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertEquals([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
        self::assertSame(1, $task->getPriority());
        self::assertSame(1, $secondTask->getPriority());
    }

    public function testTasksCanBeSortedWithNegativePriority(): void
    {
        $task = new NullTask('app', [
            'priority' => -500,
        ]);

        $secondTask = new NullTask('foo', [
            'priority' => -200,
        ]);

        $batchPolicy = new BatchPolicy();
        $sortedTasks = $batchPolicy->sort(new TaskList([
            'foo' => $secondTask,
            'app' => $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertEquals([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
        self::assertSame(-501, $task->getPriority());
        self::assertSame(-201, $secondTask->getPriority());
    }
}
