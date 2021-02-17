<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Middleware\OrderedMiddlewareInterface;
use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerMiddlewareStackTest extends TestCase
{
    public function testStackCanRunEmptyPreMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $middleware->expects(self::never())->method('postScheduling');

        $secondMiddleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('preScheduling');

        $stack = new SchedulerMiddlewareStack([
            $middleware,
        ]);

        $stack->runPreSchedulingMiddleware($task, $scheduler);
    }

    public function testStackCanRunPreMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $middleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task));

        $secondMiddleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('postScheduling');

        $stack = new SchedulerMiddlewareStack([
            $middleware,
            $secondMiddleware,
        ]);

        $stack->runPreSchedulingMiddleware($task, $scheduler);
    }

    public function testStackCanRunPreMiddlewareListWithOrderedMiddleware(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $middleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task));

        $secondMiddleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('postScheduling');

        $thirdMiddleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $thirdMiddleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task));

        $fourthMiddleware = new class() implements PreSchedulingMiddlewareInterface, OrderedMiddlewareInterface {
            public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
            {
                throw new RuntimeException('An error occurred');
            }

            public function getPriority(): int
            {
                return 1;
            }
        };

        $stack = new SchedulerMiddlewareStack([
            $middleware,
            $secondMiddleware,
            $thirdMiddleware,
            $fourthMiddleware,
        ]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('An error occurred');
        self::expectExceptionCode(0);
        $stack->runPreSchedulingMiddleware($task, $scheduler);
    }

    public function testStackCanRunEmptyPostMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $middleware->expects(self::never())->method('postScheduling');

        $secondMiddleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('preScheduling');

        $stack = new SchedulerMiddlewareStack([
            $secondMiddleware,
        ]);

        $stack->runPostSchedulingMiddleware($task, $scheduler);
    }

    public function testStackCanRunPostMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $middleware->expects(self::never())->method('preScheduling');

        $secondMiddleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::once())->method('postScheduling')->with(self::equalTo($task));

        $stack = new SchedulerMiddlewareStack([
            $middleware,
            $secondMiddleware,
        ]);

        $stack->runPostSchedulingMiddleware($task, $scheduler);
    }
}
