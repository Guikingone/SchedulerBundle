<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\MiddlewareException;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskCallbackMiddlewareTest extends TestCase
{
    public function testMiddlewareIsConfigured(): void
    {
        $taskCallbackMiddleware = new TaskCallbackMiddleware();

        self::assertSame(1, $taskCallbackMiddleware->getPriority());
    }

    public function testMiddlewareCannotBeCalledOnEmptyBeforeCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(null);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();
        $taskCallbackMiddleware->preScheduling($task, $scheduler);
    }

    public function testMiddlewareCanBeCalledOnErroredBeforeCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(fn (): bool => false);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('The task cannot be scheduled as an error occurred on the before scheduling callback');
        self::expectExceptionCode(0);
        $taskCallbackMiddleware->preScheduling($task, $scheduler);
    }

    public function testMiddlewareCanBeCalledOnValidBeforeCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(fn (): bool => true);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();
        $taskCallbackMiddleware->preScheduling($task, $scheduler);
    }

    public function testMiddlewareCannotBeCalledOnEmptyAfterCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getAfterScheduling')->willReturn(null);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();
        $taskCallbackMiddleware->postScheduling($task, $scheduler);
    }

    public function testMiddlewareCanBeCalledOnErroredAfterCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule')->with(self::equalTo('foo'));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getAfterScheduling')->willReturn(fn (): bool => false);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('The task has encountered an error after scheduling, it has been unscheduled');
        self::expectExceptionCode(0);
        $taskCallbackMiddleware->postScheduling($task, $scheduler);
    }

    public function testMiddlewareCanBeCalledOnValidAfterCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getAfterScheduling')->willReturn(fn (): bool => true);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();
        $taskCallbackMiddleware->postScheduling($task, $scheduler);
    }

    public function testMiddlewareCannotPreExecuteEmptyBeforeExecutingCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBeforeExecuting')->willReturn(null);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();
        $taskCallbackMiddleware->preExecute($task);
    }

    public function testMiddlewareCannotPreExecuteErroredBeforeExecutingCallback(): void
    {
        $nullTask = new NullTask('foo', [
            'before_executing' => fn (): bool => false,
        ]);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('The task "foo" has encountered an error when executing the SchedulerBundle\Task\NullTask::getBeforeExecuting() callback.');
        self::expectExceptionCode(0);
        $taskCallbackMiddleware->preExecute($nullTask);
    }

    public function testMiddlewareCanPreExecuteWithValidBeforeExecutingCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');
        $task->expects(self::once())->method('getBeforeExecuting')->willReturn(fn (): bool => true);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();
        $taskCallbackMiddleware->preExecute($task);
    }

    public function testMiddlewareCannotPostExecuteEmptyAfterExecutingCallback(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getAfterExecuting')->willReturn(null);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();
        $taskCallbackMiddleware->postExecute($task, $worker);
    }

    public function testMiddlewareCannotPostExecuteErroredAfterExecutingCallback(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $nullTask = new NullTask('foo', [
            'after_executing' => fn (): bool => false,
        ]);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('The task "foo" has encountered an error when executing the SchedulerBundle\Task\NullTask::getAfterExecuting() callback.');
        self::expectExceptionCode(0);
        $taskCallbackMiddleware->postExecute($nullTask, $worker);
    }

    public function testMiddlewareCanPostExecuteWithValidAfterExecutingCallback(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');
        $task->expects(self::once())->method('getAfterExecuting')->willReturn(fn (): bool => true);

        $taskCallbackMiddleware = new TaskCallbackMiddleware();
        $taskCallbackMiddleware->postExecute($task, $worker);
    }
}
