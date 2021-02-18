<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\MiddlewareException;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskCallbackMiddlewareTest extends TestCase
{
    public function testMiddlewareIsConfigured(): void
    {
        $middleware = new TaskCallbackMiddleware();

        self::assertSame(1, $middleware->getPriority());
    }

    public function testMiddlewareCannotBeCalledOnEmptyBeforeCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(null);

        $middleware = new TaskCallbackMiddleware();
        $middleware->preScheduling($task, $scheduler);
    }

    public function testMiddlewareCanBeCalledOnErroredBeforeCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getBeforeScheduling')->willReturn(fn (): bool => false);

        $middleware = new TaskCallbackMiddleware();

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('The task cannot be scheduled as en error occurred on the before scheduling callback');
        self::expectExceptionCode(0);
        $middleware->preScheduling($task, $scheduler);
    }

    public function testMiddlewareCanBeCalledOnValidBeforeCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getBeforeScheduling')->willReturn(fn (): bool => true);

        $middleware = new TaskCallbackMiddleware();
        $middleware->preScheduling($task, $scheduler);
    }

    public function testMiddlewareCannotBeCalledOnEmptyAfterCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getAfterScheduling')->willReturn(null);

        $middleware = new TaskCallbackMiddleware();
        $middleware->postScheduling($task, $scheduler);
    }

    public function testMiddlewareCanBeCalledOnErroredAfterCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule')->with(self::equalTo('foo'));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getAfterScheduling')->willReturn(fn (): bool => false);

        $middleware = new TaskCallbackMiddleware();

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('The task has encountered an error after scheduling, it has been unscheduled');
        self::expectExceptionCode(0);
        $middleware->postScheduling($task, $scheduler);
    }

    public function testMiddlewareCanBeCalledOnValidAfterCallback(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getAfterScheduling')->willReturn(fn (): bool => true);

        $middleware = new TaskCallbackMiddleware();
        $middleware->postScheduling($task, $scheduler);
    }

    public function testMiddlewareCannotPreExecuteEmptyBeforeExecutingCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBeforeExecuting')->willReturn(null);

        $middleware = new TaskCallbackMiddleware();
        $middleware->preExecute($task);
    }

    public function testMiddlewareCannotPreExecuteErroredBeforeExecutingCallback(): void
    {
        $task = new NullTask('foo', [
            'before_executing' => fn (): bool => false,
        ]);

        $middleware = new TaskCallbackMiddleware();

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('The task "foo" has encounter an error when executing the SchedulerBundle\Task\NullTask::getBeforeExecuting() callback');
        self::expectExceptionCode(0);
        $middleware->preExecute($task);
    }

    public function testMiddlewareCanPreExecuteWithValidBeforeExecutingCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');
        $task->expects(self::exactly(2))->method('getBeforeExecuting')->willReturn(fn (): bool => true);

        $middleware = new TaskCallbackMiddleware();
        $middleware->preExecute($task);
    }

    public function testMiddlewareCannotPostExecuteEmptyAfterExecutingCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getAfterExecuting')->willReturn(null);

        $middleware = new TaskCallbackMiddleware();
        $middleware->postExecute($task);
    }

    public function testMiddlewareCannotPostExecuteErroredAfterExecutingCallback(): void
    {
        $task = new NullTask('foo', [
            'after_executing' => fn (): bool => false,
        ]);

        $middleware = new TaskCallbackMiddleware();

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('The task "foo" has encounter an error when executing the SchedulerBundle\Task\NullTask::getAfterExecuting() callback');
        self::expectExceptionCode(0);
        $middleware->postExecute($task);
    }

    public function testMiddlewareCanPostExecuteWithValidAfterExecutingCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');
        $task->expects(self::exactly(2))->method('getAfterExecuting')->willReturn(fn (): bool => true);

        $middleware = new TaskCallbackMiddleware();
        $middleware->postExecute($task);
    }
}
