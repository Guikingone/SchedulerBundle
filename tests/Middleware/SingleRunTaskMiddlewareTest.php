<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\OrderedMiddlewareInterface;
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\RequiredMiddlewareInterface;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SingleRunTaskMiddlewareTest extends TestCase
{
    public function testMiddlewareIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);

        self::assertInstanceOf(PostExecutionMiddlewareInterface::class, $singleRunTaskMiddleware);
        self::assertInstanceOf(RequiredMiddlewareInterface::class, $singleRunTaskMiddleware);
        self::assertInstanceOf(OrderedMiddlewareInterface::class, $singleRunTaskMiddleware);
        self::assertSame(15, $singleRunTaskMiddleware->getPriority());
    }

    public function testMiddlewareCannotHandleInvalidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('pause');

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute($task, $worker);
    }

    public function testMiddlewareCanHandleSingleRunTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('isSingleRun')->willReturn(true);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('pause')->with(self::equalTo('foo'));

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute($task, $worker);
    }
}
