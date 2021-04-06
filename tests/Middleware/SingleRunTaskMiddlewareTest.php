<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SingleRunTaskMiddlewareTest extends TestCase
{
    public function testMiddlewareIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        self::assertSame(15, $singleRunTaskMiddleware->getPriority());
    }

    public function testMiddlewareCannotHandleInvalidTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('pause');

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute($task);
    }

    public function testMiddlewareCanHandleSingleRunTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('isSingleRun')->willReturn(true);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('pause')->with(self::equalTo('foo'));

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute($task);
    }
}
