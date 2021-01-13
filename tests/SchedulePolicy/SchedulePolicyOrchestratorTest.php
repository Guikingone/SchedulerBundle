<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\SchedulePolicy;

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
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulePolicyOrchestratorTest extends TestCase
{
    public function testSchedulePolicyCannotSortWithEmptyPolicies(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([]);

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('The tasks cannot be sorted as no policies have been defined');
        $orchestrator->sort('deadline', []);
    }

    public function testSchedulePolicyCannotSortEmptyTasks(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([
            new BatchPolicy(),
        ]);

        static::assertEmpty($orchestrator->sort('batch', []));
    }

    public function testSchedulePolicyCannotSortWithInvalidPolicy(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $orchestrator = new SchedulePolicyOrchestrator([
            new BatchPolicy(),
        ]);

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The policy "test" cannot be used');
        $orchestrator->sort('test', [$task]);
    }

    public function testSchedulePolicyCanSortTasksUsingBatch(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([
            new BatchPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);

        static::assertCount(2, $orchestrator->sort('batch', [$secondTask, $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingDeadline(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([
            new DeadlinePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new \DateInterval('P3D'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new \DateInterval('P2D'));

        static::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
        ], $orchestrator->sort('deadline', ['foo' => $secondTask, 'bar' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingExecutionDuration(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([
            new ExecutionDurationPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getExecutionComputationTime')->willReturn(10.0);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getExecutionComputationTime')->willReturn(12.0);

        static::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $orchestrator->sort('execution_duration', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingFirstInFirstOut(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getScheduledAt')->willReturn(new \DateTimeImmutable('+ 1 minute'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getScheduledAt')->willReturn(new \DateTimeImmutable('+ 2 minute'));

        static::assertSame([
            'foo' => $secondTask,
            'app' => $task,
        ], $orchestrator->sort('first_in_first_out', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingFirstInLastOut(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([
            new FirstInLastOutPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getScheduledAt')->willReturn(new \DateTimeImmutable('+ 1 minute'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getScheduledAt')->willReturn(new \DateTimeImmutable('+ 2 minute'));

        static::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $orchestrator->sort('first_in_last_out', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testTasksCanBeSortTasksUsingIdle(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([
            new IdlePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getPriority')->willReturn(-10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturn(-20);

        $tasks = $orchestrator->sort('idle', ['app' => $secondTask, 'foo' => $task]);

        static::assertCount(2, $tasks);
        static::assertSame(['foo' => $task, 'app' => $secondTask], $tasks);
    }

    public function testTasksCanBeSortTasksUsingMemoryUsage(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([
            new MemoryUsagePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getExecutionMemoryUsage')->willReturn(10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getExecutionMemoryUsage')->willReturn(15);

        static::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $orchestrator->sort('memory_usage', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingNice(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([
            new NicePolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getNice')->willReturn(1);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getNice')->willReturn(5);

        static::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $orchestrator->sort('nice', ['foo' => $secondTask, 'app' => $task]));
    }

    public function testSchedulePolicyCanSortTasksUsingRoundRobin(): void
    {
        $orchestrator = new SchedulePolicyOrchestrator([
            new RoundRobinPolicy(),
        ]);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionComputationTime')->willReturn(12.0);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getExecutionComputationTime')->willReturn(10.0);
        $secondTask->expects(self::once())->method('getMaxDuration')->willReturn(10.0);

        static::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
        ], $orchestrator->sort('round_robin', ['foo' => $secondTask, 'bar' => $task]));
    }
}
