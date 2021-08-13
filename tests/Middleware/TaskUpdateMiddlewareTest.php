<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\TaskUpdateMiddleware;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUpdateMiddlewareTest extends TestCase
{
    public function testMiddlewareIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $middleware = new TaskUpdateMiddleware($scheduler);

        self::assertSame(10, $middleware->getPriority());
    }

    public function testMiddlewareCanUpdate(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('update')
            ->with(self::equalTo('foo'), self::equalTo($task))
        ;

        $taskUpdateMiddleware = new TaskUpdateMiddleware($scheduler);
        $taskUpdateMiddleware->postExecute($task, $worker);
    }
}
