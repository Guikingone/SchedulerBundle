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
use SchedulerBundle\SchedulePolicy\RoundRobinPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;

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
        $schedulePolicyOrchestrator->sort('deadline', new TaskList([]));
    }

    public function testSchedulePolicyCannotSortEmptyTasks(): void
    {
        $list = new TaskList([]);
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new BatchPolicy(),
        ]);

        self::assertSame($list, $schedulePolicyOrchestrator->sort('batch', $list));
    }

    public function testSchedulePolicyCannotSortWithInvalidPolicy(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new BatchPolicy(),
            new FirstInFirstOutPolicy(),
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The policy "test" cannot be used');
        self::expectExceptionCode(0);
        $schedulePolicyOrchestrator->sort('test', new TaskList([
            new NullTask('foo'),
        ]));
    }

    public function testSchedulePolicyCanSortTasksUsingBatch(): void
    {
        $task = new NullTask('app', [
            'priority' => 2,
        ]);

        $secondTask = new NullTask('foo', [
            'priority' => 2,
        ]);

        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new BatchPolicy(),
        ]);
        $sortedTasks = $schedulePolicyOrchestrator->sort('batch', new TaskList([
            $secondTask,
            $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertSame([
            'foo' => $secondTask,
            'app' => $task,
        ], $sortedTasks->toArray());
        self::assertSame(1, $task->getPriority());
        self::assertSame(1, $secondTask->getPriority());
    }

    public function testSchedulePolicyCanSortTasksUsingDeadline(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new DeadlinePolicy(),
        ]);

        $task = new NullTask('bar', [
            'arrival_time' => new DateTimeImmutable('+ 1 month'),
            'execution_relative_deadline' => new DateInterval('P2D'),
            'execution_absolute_deadline' => new DateInterval('P3D'),
        ]);

        $secondTask = new NullTask('foo', [
            'arrival_time' => new DateTimeImmutable('+ 1 month'),
            'execution_relative_deadline' => new DateInterval('P2D'),
            'execution_absolute_deadline' => new DateInterval('P2D'),
        ]);

        $sortedTasks = $schedulePolicyOrchestrator->sort('deadline', new TaskList([
            $secondTask,
            $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertEquals([
            'bar' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }

    public function testSchedulePolicyCanSortTasksUsingExecutionDuration(): void
    {
        $task = new NullTask('app', [
            'execution_computation_time' => 10.0,
        ]);

        $secondTask = new NullTask('foo', [
            'execution_computation_time' => 12.0,
        ]);

        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new ExecutionDurationPolicy(),
        ]);
        $sortedTasks = $schedulePolicyOrchestrator->sort('execution_duration', new TaskList([
            $secondTask,
            $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }

    public function testSchedulePolicyCanSortTasksUsingFirstInFirstOut(): void
    {
        $task = new NullTask('app', [
            'scheduled_at' => new DateTimeImmutable('+ 1 minute'),
        ]);

        $secondTask = new NullTask('foo', [
            'scheduled_at' => new DateTimeImmutable('+ 2 minute'),
        ]);

        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new BatchPolicy(),
            new FirstInFirstOutPolicy(),
        ]);
        $sortedTasks = $schedulePolicyOrchestrator->sort('first_in_first_out', new TaskList([
            $secondTask,
            $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }

    public function testSchedulePolicyCanSortTasksUsingFirstInLastOut(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new FirstInLastOutPolicy(),
        ]);

        $task = new NullTask('app', [
            'scheduled_at' => new DateTimeImmutable('+ 1 minute'),
        ]);

        $secondTask = new NullTask('foo', [
            'scheduled_at' => new DateTimeImmutable('+ 2 minute'),
        ]);

        $sortedTasks = $schedulePolicyOrchestrator->sort('first_in_last_out', new TaskList([
            $secondTask,
            $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }

    public function testSchedulePolicyCanSortTasksUsingIdle(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new IdlePolicy(),
        ]);

        $task = new NullTask('foo', [
            'priority' => -10,
        ]);

        $secondTask = new NullTask('app', [
            'priority' => -20,
        ]);

        $sortedTasks = $schedulePolicyOrchestrator->sort('idle', new TaskList([
            $secondTask,
            $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertSame(['foo' => $task, 'app' => $secondTask], $sortedTasks->toArray());
    }

    public function testSchedulePolicyCanSortTasksUsingMemoryUsage(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new MemoryUsagePolicy(),
        ]);

        $task = new NullTask('app', [
            'execution_memory_usage' => 10,
        ]);

        $secondTask = new NullTask('foo', [
            'execution_memory_usage' => 15,
        ]);

        $sortedTasks = $schedulePolicyOrchestrator->sort('memory_usage', new TaskList([
            $secondTask,
            $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }

    public function testSchedulePolicyCanSortTasksUsingNice(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new NicePolicy(),
        ]);

        $task = new NullTask('app', [
            'nice' => 1,
        ]);

        $secondTask = new NullTask('foo', [
            'nice' => 5,
        ]);

        $sortedTasks = $schedulePolicyOrchestrator->sort('nice', new TaskList([
            $secondTask,
            $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }

    public function testSchedulePolicyCanSortTasksUsingRoundRobin(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
            new RoundRobinPolicy(),
        ]);

        $task = new NullTask('bar', [
            'execution_computation_time' => 12.0,
        ]);

        $secondTask = new NullTask('foo', [
            'execution_computation_time' => 10.0,
            'max_duration' => 10.0,
        ]);

        $sortedTasks = $schedulePolicyOrchestrator->sort('round_robin', new TaskList([
            $secondTask,
            $task,
        ]));

        self::assertCount(2, $sortedTasks);
        self::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }

    public function testSchedulePolicyCanSortNestedTasksUsingBatch(): void
    {
        $task = new NullTask('foo', [
            'priority' => 2,
        ]);

        $secondTask = new NullTask('bar', [
            'priority' => 3,
        ]);

        $chainedTask = new ChainedTask('nested');
        $chainedTask->setTasks(new TaskList([$secondTask, $task]));

        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new BatchPolicy(),
        ]);
        $schedulePolicyOrchestrator->sort('batch', new TaskList([$chainedTask]));

        $subTasks = $chainedTask->getTasks();
        self::assertCount(2, $subTasks);
        self::assertSame([
            'foo' => $task,
            'bar' => $secondTask,
        ], $subTasks->toArray());

        $fooTask = $subTasks->get('foo');
        self::assertSame(1, $fooTask->getPriority());

        $barTask = $subTasks->get('bar');
        self::assertSame(2, $barTask->getPriority());
    }

    public function testSchedulePolicyCanSortNestedTasksUsingDeadline(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new DeadlinePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getArrivalTime')->willReturn(new DateTimeImmutable('+ 1 month'));
        $task->expects(self::once())->method('getExecutionRelativeDeadline')->willReturn(new DateInterval('P2D'));
        $task->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P3D'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('bar');
        $secondTask->expects(self::once())->method('getArrivalTime')->willReturn(new DateTimeImmutable('+ 1 month'));
        $secondTask->expects(self::once())->method('getExecutionRelativeDeadline')->willReturn(new DateInterval('P2D'));
        $secondTask->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P2D'));

        $chainedTask = new ChainedTask('nested');
        $chainedTask->setArrivalTime(new DateTimeImmutable());
        $chainedTask->setExecutionStartTime(new DateTimeImmutable());
        $chainedTask->setExecutionRelativeDeadline(new DateInterval('P1D'));
        $chainedTask->setTasks(new TaskList([$secondTask, $task]));

        $schedulePolicyOrchestrator->sort('deadline', new TaskList([$chainedTask]));

        self::assertSame([
            $task,
            $secondTask,
        ], $chainedTask->getTasks()->toArray(false));
    }

    public function testSchedulePolicyCanSortNestedTasksUsingExecutionDuration(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new ExecutionDurationPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExecutionComputationTime')->willReturn(10.0);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('bar');
        $secondTask->expects(self::once())->method('getExecutionComputationTime')->willReturn(12.0);

        $chainedTask = new ChainedTask('nested');
        $chainedTask->setTasks(new TaskList([$secondTask, $task]));

        $schedulePolicyOrchestrator->sort('execution_duration', new TaskList([$chainedTask]));

        self::assertSame([
            $task,
            $secondTask,
        ], $chainedTask->getTasks()->toArray(false));
    }

    public function testSchedulePolicyCanSortNestedTasksUsingFirstInFirstOut(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 1 minute'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('bar');
        $secondTask->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 2 minute'));

        $chainedTask = new ChainedTask('nested');
        $chainedTask->setTasks(new TaskList([$secondTask, $task]));

        $schedulePolicyOrchestrator->sort('first_in_first_out', new TaskList([$chainedTask]));

        self::assertSame([
            $task,
            $secondTask,
        ], $chainedTask->getTasks()->toArray(false));
    }

    public function testSchedulePolicyCanSortNestedTasksUsingFirstInLastOut(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new FirstInLastOutPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 1 minute'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('bar');
        $secondTask->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 2 minute'));

        $chainedTask = new ChainedTask('nested');
        $chainedTask->setTasks(new TaskList([$secondTask, $task]));

        $schedulePolicyOrchestrator->sort('first_in_last_out', new TaskList([$chainedTask]));

        self::assertSame([
            $task,
            $secondTask,
        ], $chainedTask->getTasks()->toArray(false));
    }

    public function testSchedulePolicyCanSortNestedTasksUsingIdle(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new IdlePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getPriority')->willReturn(-10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('bar');
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturn(-20);

        $chainedTask = new ChainedTask('nested');
        $chainedTask->setTasks(new TaskList([$secondTask, $task]));

        $schedulePolicyOrchestrator->sort('idle', new TaskList([$chainedTask]));

        self::assertSame([
            $task,
            $secondTask,
        ], $chainedTask->getTasks()->toArray(false));
    }

    public function testSchedulePolicyCanSortNestedTasksUsingMemoryUsage(): void
    {
        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new MemoryUsagePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->method('getExecutionMemoryUsage')->willReturn(10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('bar');
        $secondTask->method('getExecutionMemoryUsage')->willReturn(15);

        $chainedTask = new ChainedTask('nested');
        $chainedTask->setTasks(new TaskList([$secondTask, $task]));

        $schedulePolicyOrchestrator->sort('memory_usage', new TaskList([$chainedTask]));

        self::assertSame([
            $task,
            $secondTask,
        ], $chainedTask->getTasks()->toArray(false));
    }

    public function testSchedulePolicyCanSortNestedTasksUsingNice(): void
    {
        $task = new NullTask('foo', [
            'nice' => 1,
        ]);

        $secondTask = new NullTask('bar', [
            'nice' => 5,
        ]);

        $chainedTask = new ChainedTask('nested');
        $chainedTask->setTasks(new TaskList([
            $secondTask,
            $task,
        ]));

        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new NicePolicy(),
        ]);
        $schedulePolicyOrchestrator->sort('nice', new TaskList([$chainedTask]));

        self::assertSame([
            $task,
            $secondTask,
        ], $chainedTask->getTasks()->toArray(false));
    }

    public function testSchedulePolicyCanSortNestedTasksUsingRoundRobin(): void
    {
        $task = new NullTask('foo', [
            'execution_computation_time' => 12.0,
        ]);

        $secondTask = new NullTask('bar', [
            'execution_computation_time' => 10.0,
            'max_duration' => 10.0,
        ]);

        $chainedTask = new ChainedTask('nested');
        $chainedTask->setTasks(new TaskList([$secondTask, $task]));

        $schedulePolicyOrchestrator = new SchedulePolicyOrchestrator([
            new RoundRobinPolicy(),
        ]);
        $schedulePolicyOrchestrator->sort('round_robin', new TaskList([$chainedTask]));

        self::assertSame([
            $task,
            $secondTask,
        ], $chainedTask->getTasks()->toArray(false));
    }
}
