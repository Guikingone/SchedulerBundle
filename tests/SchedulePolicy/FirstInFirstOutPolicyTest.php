<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;

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
        $task = $this->createMock(TaskInterface::class);
        $task->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 1 minute'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 2 minute'));

        $thirdTask = $this->createMock(TaskInterface::class);
        $thirdTask->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 1 minute'));

        $firstInFirstOutPolicy = new FirstInFirstOutPolicy();

        self::assertSame([
            'app' => $task,
            'random' => $thirdTask,
            'foo' => $secondTask,
        ], $firstInFirstOutPolicy->sort(['foo' => $secondTask, 'app' => $task, 'random' => $thirdTask]));
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

        self::assertSame([
            'random' => $thirdTask,
            'bar' => $secondTask,
            'foo' => $task,
        ], $firstInFirstOutPolicy->sort(['bar' => $secondTask, 'random' => $thirdTask, 'foo' => $task]));
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

        self::assertSame([
            'qux' => $task,
            'foo' => $secondTask,
            'bar' => $thirdTask,
            'baz' => $fourthTask,
        ], $firstInFirstOutPolicy->sort(['foo' => $secondTask, 'baz' => $fourthTask, 'bar' => $thirdTask, 'qux' => $task]));
    }
}
