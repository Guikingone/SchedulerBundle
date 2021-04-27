<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use RuntimeException;
use InvalidArgumentException;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\BatchPolicy;
use SchedulerBundle\SchedulePolicy\DeadlinePolicy;
use SchedulerBundle\SchedulePolicy\ExecutionDurationPolicy;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\FirstInLastOutPolicy;
use SchedulerBundle\SchedulePolicy\IdlePolicy;
use SchedulerBundle\SchedulePolicy\MemoryUsagePolicy;
use SchedulerBundle\SchedulePolicy\NicePolicy;
use SchedulerBundle\SchedulePolicy\PriorityPolicy;
use SchedulerBundle\SchedulePolicy\RoundRobinPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulePolicyOrchestratorTest extends TestCase
{
    public function testSchedulePolicyCannotSortWithEmptyPolicies(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The tasks cannot be sorted as no policies have been defined');
        self::expectExceptionCode(0);
        $schedulePolicyOrchestrator->sort('deadline', []);
    }

    public function testSchedulePolicyCannotSortEmptyTasks(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new BatchPolicy(),
        ]);

        self::assertEmpty($schedulePolicyOrchestrator->sort('batch', []));
    }

    public function testSchedulePolicyCannotSortWithInvalidPolicy(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new BatchPolicy(),
            new FirstInFirstOutPolicy(),
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The policy "test" cannot be used');
        self::expectExceptionCode(0);
        $schedulePolicyOrchestrator->sort('test', [$task]);
    }

    public function testSchedulePolicyCanSortTasksUsingBatch(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new BatchPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);

        self::assertCount(2, $schedulePolicyOrchestrator->sort('batch', [$secondTask, $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingDeadline(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new DeadlinePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P3D'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P2D'));

        self::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
        ], $schedulePolicyOrchestrator->sort('deadline', ['foo' => $secondTask, 'bar' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingExecutionDuration(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new ExecutionDurationPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionComputationTime')->willReturn(10.0);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getExecutionComputationTime')->willReturn(12.0);

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $schedulePolicyOrchestrator->sort('execution_duration', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingFirstInFirstOut(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new BatchPolicy(),
            new FirstInFirstOutPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 1 minute'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 2 minute'));

        self::assertSame([
            'foo' => $secondTask,
            'app' => $task,
        ], $schedulePolicyOrchestrator->sort('first_in_first_out', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingFirstInLastOut(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new FirstInLastOutPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 1 minute'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 2 minute'));

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $schedulePolicyOrchestrator->sort('first_in_last_out', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testTasksCanBeSortTasksUsingIdle(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new IdlePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getPriority')->willReturn(-10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturn(-20);

        $tasks = $schedulePolicyOrchestrator->sort('idle', ['app' => $secondTask, 'foo' => $task]);

        self::assertCount(2, $tasks);
        self::assertSame(['foo' => $task, 'app' => $secondTask], $tasks);
    }

    public function testTasksCanBeSortTasksUsingMemoryUsage(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new MemoryUsagePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getExecutionMemoryUsage')->willReturn(10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getExecutionMemoryUsage')->willReturn(15);

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $schedulePolicyOrchestrator->sort('memory_usage', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingNice(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new NicePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getNice')->willReturn(1);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getNice')->willReturn(5);

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $schedulePolicyOrchestrator->sort('nice', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingPriority(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new PriorityPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getPriority')->willReturn(-10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getPriority')->willReturn(0);

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $schedulePolicyOrchestrator->sort('priority', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingRoundRobin(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new RoundRobinPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionComputationTime')->willReturn(12.0);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getExecutionComputationTime')->willReturn(10.0);
        $secondTask->expects(self::once())->method('getMaxDuration')->willReturn(10.0);

        self::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
        ], $schedulePolicyOrchestrator->sort('round_robin', ['foo' => $secondTask, 'bar' => $task]));
    }
}
