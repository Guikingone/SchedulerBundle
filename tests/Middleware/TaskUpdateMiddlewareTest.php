<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\TaskUpdateMiddleware;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUpdateMiddlewareTest extends TestCase
{
    public function testMiddlewareCanUpdate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('update')
            ->with(self::equalTo('foo'), self::equalTo($task))
        ;

        $taskUpdateMiddleware = new TaskUpdateMiddleware($scheduler);
        $taskUpdateMiddleware->postExecute($task);
    }
}
