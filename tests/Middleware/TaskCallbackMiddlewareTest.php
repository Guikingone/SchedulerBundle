<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\SchedulerInterface;
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

        self::expectException(RuntimeException::class);
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

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task has encounter an error after scheduling, it has been unscheduled');
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
}
