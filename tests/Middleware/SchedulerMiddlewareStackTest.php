<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Middleware\OrderedMiddlewareInterface;
use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\RequiredMiddlewareInterface;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use Tests\SchedulerBundle\Middleware\Assets\OrderedMiddleware;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerMiddlewareStackTest extends TestCase
{
    /**
     * @throws Throwable {@see SchedulerMiddlewareStack::runPreSchedulingMiddleware()}
     */
    public function testStackCanRunEmptyPreMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $orderedMiddleware = $this->createMock(OrderedMiddlewareInterface::class);
        $orderedMiddleware->expects(self::never())->method('getPriority');

        $middleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $middleware->expects(self::never())->method('postScheduling');

        $secondMiddleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('preScheduling');

        $schedulerMiddlewareStack = new SchedulerMiddlewareStack([
            $middleware,
        ]);

        $schedulerMiddlewareStack->runPreSchedulingMiddleware($task, $scheduler);
    }

    /**
     * @throws Throwable {@see SchedulerMiddlewareStack::runPreSchedulingMiddleware()}
     */
    public function testStackCanRunPreMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $middleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task));

        $secondMiddleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('postScheduling');

        $schedulerMiddlewareStack = new SchedulerMiddlewareStack([
            $middleware,
            $secondMiddleware,
        ]);

        $schedulerMiddlewareStack->runPreSchedulingMiddleware($task, $scheduler);
    }

    /**
     * @throws Throwable {@see SchedulerMiddlewareStack::runPreSchedulingMiddleware()}
     */
    public function testStackCannotRunPreMiddlewareListWithErroredOrderedMiddleware(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $middleware->expects(self::never())->method('preScheduling');

        $secondMiddleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('postScheduling');

        $thirdMiddleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $thirdMiddleware->expects(self::never())->method('preScheduling');

        $fourthMiddleware = new class () implements PreSchedulingMiddlewareInterface, OrderedMiddlewareInterface {
            public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
            {
                throw new RuntimeException('An error occurred');
            }

            public function getPriority(): int
            {
                return 1;
            }
        };

        $schedulerMiddlewareStack = new SchedulerMiddlewareStack([
            $middleware,
            $secondMiddleware,
            $thirdMiddleware,
            $fourthMiddleware,
        ]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('An error occurred');
        self::expectExceptionCode(0);
        $schedulerMiddlewareStack->runPreSchedulingMiddleware($task, $scheduler);
    }

    /**
     * @throws Throwable {@see SchedulerMiddlewareStack::runPreSchedulingMiddleware()}
     */
    public function testStackCanRunOrderedPreMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $orderedMiddleware = $this->createMock(OrderedMiddleware::class);
        $orderedMiddleware->expects(self::exactly(3))->method('getPriority')->willReturn(1);
        $orderedMiddleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task), self::equalTo($scheduler));

        $secondOrderedMiddleware = $this->createMock(OrderedMiddleware::class);
        $secondOrderedMiddleware->expects(self::exactly(3))->method('getPriority')->willReturn(2);
        $secondOrderedMiddleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task), self::equalTo($scheduler));

        $thirdOrderedMiddleware = $this->createMock(OrderedMiddleware::class);
        $thirdOrderedMiddleware->expects(self::exactly(2))->method('getPriority')->willReturn(0);
        $thirdOrderedMiddleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task), self::equalTo($scheduler));

        $fourthOrderedMiddleware = $this->createMock(OrderedMiddleware::class);
        $fourthOrderedMiddleware->expects(self::exactly(2))->method('getPriority')->willReturn(1);
        $fourthOrderedMiddleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task), self::equalTo($scheduler));

        $middleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $middleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task), self::equalTo($scheduler));

        $secondMiddleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('postScheduling');

        $schedulerMiddlewareStack = new SchedulerMiddlewareStack([
            $middleware,
            $secondMiddleware,
            $orderedMiddleware,
            $secondOrderedMiddleware,
            $thirdOrderedMiddleware,
            $fourthOrderedMiddleware,
        ]);

        $schedulerMiddlewareStack->runPreSchedulingMiddleware($task, $scheduler);
    }

    /**
     * @throws Throwable {@see SchedulerMiddlewareStack::runPreSchedulingMiddleware()}
     */
    public function testStackCanRunSingleOrderedPreMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $orderedMiddleware = $this->createMock(OrderedMiddleware::class);
        $orderedMiddleware->expects(self::never())->method('getPriority');
        $orderedMiddleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task), self::equalTo($scheduler));

        $middleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $middleware->expects(self::once())->method('preScheduling')->with(self::equalTo($task), self::equalTo($scheduler));

        $secondMiddleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('postScheduling');

        $schedulerMiddlewareStack = new SchedulerMiddlewareStack([
            $middleware,
            $secondMiddleware,
            $orderedMiddleware,
        ]);

        $schedulerMiddlewareStack->runPreSchedulingMiddleware($task, $scheduler);
    }

    /**
     * @throws Throwable {@see SchedulerMiddlewareStack::runPostSchedulingMiddleware()}
     */
    public function testStackCanRunEmptyPostMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $middleware->expects(self::never())->method('postScheduling');

        $secondMiddleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('preScheduling');

        $schedulerMiddlewareStack = new SchedulerMiddlewareStack([
            $secondMiddleware,
        ]);

        $schedulerMiddlewareStack->runPostSchedulingMiddleware($task, $scheduler);
    }

    /**
     * @throws Throwable {@see SchedulerMiddlewareStack::runPostSchedulingMiddleware()}
     */
    public function testStackCanRunPostMiddlewareList(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $middleware = $this->createMock(PreSchedulingMiddlewareInterface::class);
        $middleware->expects(self::never())->method('preScheduling');

        $secondMiddleware = $this->createMock(PostSchedulingMiddlewareInterface::class);
        $secondMiddleware->expects(self::once())->method('postScheduling')->with(self::equalTo($task));

        $schedulerMiddlewareStack = new SchedulerMiddlewareStack([
            $middleware,
            $secondMiddleware,
        ]);

        $schedulerMiddlewareStack->runPostSchedulingMiddleware($task, $scheduler);
    }

    /**
     * @throws Throwable {@see SchedulerMiddlewareStack::runPostSchedulingMiddleware()}
     */
    public function testStackCanRunPostMiddlewareListWithRequiredMiddleware(): void
    {
        $task = new NullTask('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);

        $erroredMiddleware = new class () implements PreSchedulingMiddlewareInterface, OrderedMiddlewareInterface {
            /**
             * {@inheritdoc}
             */
            public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
            {
                throw new RuntimeException('An error occurred');
            }

            /**
             * {@inheritdoc}
             */
            public function getPriority(): int
            {
                return 1;
            }
        };

        $requiredMiddleware = new class () implements PostSchedulingMiddlewareInterface, RequiredMiddlewareInterface {
            /**
             * {@inheritdoc}
             */
            public function postScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
            {
                $task->setDescription('Description');
            }
        };

        $schedulerMiddlewareStack = new SchedulerMiddlewareStack([
            $erroredMiddleware,
            $requiredMiddleware,
        ]);

        $schedulerMiddlewareStack->runPostSchedulingMiddleware($task, $scheduler);

        self::assertSame('Description', $task->getDescription());
    }
}
