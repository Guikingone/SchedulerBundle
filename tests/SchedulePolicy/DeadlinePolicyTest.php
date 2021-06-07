<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulePolicy\DeadlinePolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DeadlinePolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $deadlinePolicy = new DeadlinePolicy();

        self::assertFalse($deadlinePolicy->support('test'));
        self::assertTrue($deadlinePolicy->support('deadline'));
    }

    public function testTasksCannotBeSortedWithoutArrivalTime(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P3D'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('foo');
        $secondTask->expects(self::never())->method('getExecutionRelativeDeadline')->willReturn(null);
        $secondTask->expects(self::never())->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P1D'));

        $deadlinePolicy = new DeadlinePolicy();

        self::expectException(RuntimeException::class);
        self::expectDeprecationMessage('The arrival time must be defined, consider executing the task "foo" first');
        self::expectExceptionCode(0);
        $deadlinePolicy->sort(['foo' => $secondTask, 'bar' => $task]);
    }

    public function testTasksCannotBeSortedWithoutExecutionRelativeDeadline(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P3D'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::never())->method('getName')->willReturn('foo');
        $secondTask->expects(self::once())->method('getArrivalTime')->willReturn(new DateTimeImmutable('+ 1 month'));
        $secondTask->expects(self::once())->method('getExecutionRelativeDeadline')->willReturn(null);
        $secondTask->expects(self::never())->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P1D'));

        $deadlinePolicy = new DeadlinePolicy();

        self::expectException(RuntimeException::class);
        self::expectDeprecationMessage('The execution relative deadline must be defined, consider using SchedulerBundle\Task\TaskInterface::setExecutionRelativeDeadline()');
        self::expectExceptionCode(0);
        $deadlinePolicy->sort(['foo' => $secondTask, 'bar' => $task]);
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getArrivalTime')->willReturn(new DateTimeImmutable('+ 1 month'));
        $task->expects(self::once())->method('getExecutionRelativeDeadline')->willReturn(new DateInterval('P2D'));
        $task->expects(self::exactly(2))->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P3D'));
        $task->expects(self::once())->method('setExecutionAbsoluteDeadline');

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getArrivalTime')->willReturn(new DateTimeImmutable('+ 1 month'));
        $secondTask->expects(self::once())->method('getExecutionRelativeDeadline')->willReturn(new DateInterval('P2D'));
        $secondTask->expects(self::exactly(2))->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P2D'));
        $secondTask->expects(self::once())->method('setExecutionAbsoluteDeadline');

        $thirdTask = $this->createMock(TaskInterface::class);
        $thirdTask->expects(self::once())->method('getArrivalTime')->willReturn(new DateTimeImmutable('+ 1 month'));
        $thirdTask->expects(self::once())->method('getExecutionRelativeDeadline')->willReturn(new DateInterval('P2D'));
        $thirdTask->expects(self::exactly(2))->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P1D'));
        $thirdTask->expects(self::once())->method('setExecutionAbsoluteDeadline');

        $deadlinePolicy = new DeadlinePolicy();

        self::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
            'random' => $thirdTask,
        ], $deadlinePolicy->sort(['foo' => $secondTask, 'bar' => $task, 'random' => $thirdTask]));
    }
}
