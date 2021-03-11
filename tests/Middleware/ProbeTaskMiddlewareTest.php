<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\ProbeTaskMiddleware;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTaskMiddlewareTest extends TestCase
{
    public function testMiddlewareCannotBeCalledOnInvalidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('isRunning');

        $task = $this->createMock(TaskInterface::class);

        $middleware = new ProbeTaskMiddleware($worker);
        $middleware->preExecute($task);
    }

    public function testMiddlewareCannotBeCalledOnTaskWithoutDelay(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('isRunning');

        $task = new ProbeTask('foo', '/_probe');

        $middleware = new ProbeTaskMiddleware($worker);
        $middleware->preExecute($task);
    }

    public function testMiddlewareCannotBeCalledOnTaskWithDelayButWithoutARunningWorker(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(true);

        $task = new ProbeTask('foo', '/_probe', false, 1000);

        $middleware = new ProbeTaskMiddleware($worker);
        $middleware->preExecute($task);
    }

    public function testMiddlewareCannotBeCalledOnTaskWithDelayAndWithRunningWorker(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(false);

        $task = new ProbeTask('foo', '/_probe', false, 1000);

        $middleware = new ProbeTaskMiddleware($worker);
        $middleware->preExecute($task);
    }
}
