<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\TaskExecutionMiddleware;
use SchedulerBundle\Task\TaskInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutionMiddlewareTest extends TestCase
{
    public function testMiddlewareIsConfigured(): void
    {
        $middleware = new TaskExecutionMiddleware();

        self::assertSame(1, $middleware->getPriority());
    }

    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     */
    public function testMiddlewareCannotDelayExecutionWithoutAnExecutionDelay(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionDelay')->willReturn(null);

        $middleware = new TaskExecutionMiddleware();
        $middleware->preExecute($task);
    }

    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     */
    public function testMiddlewareCanDelayExecutionWithAnExecutionDelay(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionDelay')->willReturn(200);

        $middleware = new TaskExecutionMiddleware();
        $middleware->preExecute($task);
    }
}
