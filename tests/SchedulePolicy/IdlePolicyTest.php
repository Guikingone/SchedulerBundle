<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\IdlePolicy;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class IdlePolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $idlePolicy = new IdlePolicy();

        self::assertFalse($idlePolicy->support('test'));
        self::assertTrue($idlePolicy->support('idle'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = new NullTask('foo', [
            'priority' => -10,
        ]);

        $secondTask = new NullTask('app', [
            'priority' => -20,
        ]);

        $idlePolicy = new IdlePolicy();
        $sortedTasks = $idlePolicy->sort(new TaskList([$secondTask, $task]));

        self::assertCount(2, $sortedTasks);
        self::assertSame([
            'foo' => $task,
            'app' => $secondTask,
        ], $sortedTasks->toArray());
    }

    public function testTasksCanBeSortedWithSamePriority(): void
    {
        $task = new NullTask('foo', [
            'priority' => 10,
        ]);

        $secondTask = new NullTask('app', [
            'priority' => 10,
        ]);

        $idlePolicy = new IdlePolicy();
        $tasks = $idlePolicy->sort(new TaskList([$secondTask, $task]));

        self::assertCount(2, $tasks);
        self::assertSame([
            'app' => $secondTask,
            'foo' => $task,
        ], $tasks->toArray());
    }

    public function testTasksCanBeSortedWithPolicyPriority(): void
    {
        $task = new NullTask('foo', [
            'priority' => 19,
        ]);

        $secondTask = new NullTask('app', [
            'priority' => 19,
        ]);

        $idlePolicy = new IdlePolicy();
        $tasks = $idlePolicy->sort(new TaskList([$secondTask, $task]));

        self::assertCount(2, $tasks);
        self::assertSame([
            'app' => $secondTask,
            'foo' => $task,
        ], $tasks->toArray());
    }
}
