<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\ProbeTaskMiddleware;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @group time-sensitive
 */
final class ProbeTaskMiddlewareTest extends TestCase
{
    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     */
    public function testMiddlewareCannotBeCalledOnInvalidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('isRunning');

        $task = $this->createMock(TaskInterface::class);

        $middleware = new ProbeTaskMiddleware();
        $middleware->preExecute($task);
    }

    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     */
    public function testMiddlewareCannotBeCalledOnTaskWithoutDelay(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('isRunning');

        $task = new ProbeTask('foo', '/_probe');

        $middleware = new ProbeTaskMiddleware();
        $middleware->preExecute($task);
    }

    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     */
    public function testMiddlewareCanBeCalledOnTaskWithDelay(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('isRunning');

        $task = new ProbeTask('foo', '/_probe', false, 1000);

        $middleware = new ProbeTaskMiddleware();
        $middleware->preExecute($task);
    }
}
