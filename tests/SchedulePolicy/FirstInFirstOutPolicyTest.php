<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FirstInFirstOutPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $firstInFirstOutPolicy = new FirstInFirstOutPolicy();

        self::assertFalse($firstInFirstOutPolicy->support('test'));
        self::assertTrue($firstInFirstOutPolicy->support('first_in_first_out'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = new NullTask('app', [
            'scheduled_at' => new DateTimeImmutable('+ 1 minute'),
        ]);

        $secondTask = new NullTask('foo', [
            'scheduled_at' => new DateTimeImmutable('+ 2 minute'),
        ]);

        $thirdTask = new NullTask('random', [
            'scheduled_at' => new DateTimeImmutable('+ 1 minute'),
        ]);

        $firstInFirstOutPolicy = new FirstInFirstOutPolicy();
        $sortedTasks = $firstInFirstOutPolicy->sort(new TaskList([$task, $secondTask, $thirdTask]));

        self::assertSame([
            'app' => $task,
            'random' => $thirdTask,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }

    public function testTasksCanBeSortedUsingNegativeDate(): void
    {
        $task = new NullTask('foo', [
            'scheduled_at' => new DateTimeImmutable('- 1 month'),
        ]);

        $secondTask = new NullTask('bar', [
            'scheduled_at' => new DateTimeImmutable('- 2 month'),
        ]);

        $thirdTask = new NullTask('random', [
            'scheduled_at' => new DateTimeImmutable('- 3 month'),
        ]);

        $firstInFirstOutPolicy = new FirstInFirstOutPolicy();
        $sortedTasks = $firstInFirstOutPolicy->sort(new TaskList([$task, $secondTask, $thirdTask]));

        self::assertSame([
            'random' => $thirdTask,
            'bar' => $secondTask,
            'foo' => $task,
        ], $sortedTasks->toArray());
    }

    public function testTasksCanBeSortedUsingDefaultDate(): void
    {
        $task = new NullTask('qux', [
            'scheduled_at' => new DateTimeImmutable(),
        ]);

        $secondTask = new NullTask('foo', [
            'scheduled_at' => new DateTimeImmutable(),
        ]);

        $thirdTask = new NullTask('bar', [
            'scheduled_at' => new DateTimeImmutable(),
        ]);

        $fourthTask = new NullTask('baz', [
            'scheduled_at' => new DateTimeImmutable(),
        ]);

        $firstInFirstOutPolicy = new FirstInFirstOutPolicy();
        $sortedTasks = $firstInFirstOutPolicy->sort(new TaskList([$task, $secondTask, $thirdTask, $fourthTask]));

        self::assertSame([
            'qux' => $task,
            'foo' => $secondTask,
            'bar' => $thirdTask,
            'baz' => $fourthTask,
        ], $sortedTasks->toArray());
    }
}
