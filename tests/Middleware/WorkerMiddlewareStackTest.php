<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerMiddlewareStackTest extends TestCase
{
    public function testStackCanRunEmptyPreMiddlewareList(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PostExecutionMiddlewareInterface::class);
        $middleware->expects(self::never())->method('postExecute')->with($task);

        $workerMiddlewareStack = new WorkerMiddlewareStack([
            $middleware,
        ]);

        $workerMiddlewareStack->runPreExecutionMiddleware($task);
    }

    public function testStackCanRunPreMiddlewareList(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PreExecutionMiddlewareInterface::class);
        $middleware->expects(self::once())->method('preExecute')->with($task);

        $secondMiddleware = $this->createMock(PostExecutionMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('postExecute')->with($task);

        $workerMiddlewareStack = new WorkerMiddlewareStack([
            $middleware,
            $secondMiddleware,
        ]);

        $workerMiddlewareStack->runPreExecutionMiddleware($task);
    }

    public function testStackCanRunEmptyPostMiddlewareList(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PreExecutionMiddlewareInterface::class);
        $middleware->expects(self::never())->method('preExecute')->with($task);

        $workerMiddlewareStack = new WorkerMiddlewareStack([
            $middleware,
        ]);

        $workerMiddlewareStack->runPostExecutionMiddleware($task);
    }

    public function testStackCanRunPostMiddlewareList(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PostExecutionMiddlewareInterface::class);
        $middleware->expects(self::once())->method('postExecute')->with($task);

        $secondMiddleware = $this->createMock(PreExecutionMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('preExecute')->with($task);

        $workerMiddlewareStack = new WorkerMiddlewareStack([
            $middleware,
            $secondMiddleware,
        ]);

        $workerMiddlewareStack->runPostExecutionMiddleware($task);
    }
}
