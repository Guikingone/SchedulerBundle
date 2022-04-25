<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Middleware\FiberAwareWorkerMiddlewareStack;
use SchedulerBundle\Middleware\MiddlewareRegistry;
use SchedulerBundle\Middleware\OrderedMiddlewareInterface;
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @requires PHP 8.1
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberAwareWorkerMiddlewareStackTest extends TestCase
{
    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     */
    public function testStackCanRunEmptyPreMiddlewareList(): void
    {
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $middleware = $this->createMock(PostExecutionMiddlewareInterface::class);
        $middleware->expects(self::never())->method('postExecute')->with($task);

        $workerMiddlewareStack = new FiberAwareWorkerMiddlewareStack(new WorkerMiddlewareStack(new MiddlewareRegistry([
            $middleware,
        ])), $logger);

        $workerMiddlewareStack->runPreExecutionMiddleware($task);
    }

    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     */
    public function testStackCanRunPreMiddlewareList(): void
    {
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $middleware = $this->createMock(PreExecutionMiddlewareInterface::class);
        $middleware->expects(self::never())->method('preExecute')->with($task);

        $secondMiddleware = $this->createMock(PostExecutionMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('postExecute')->with($task);

        $erroredMiddleware = new class () implements PreExecutionMiddlewareInterface, OrderedMiddlewareInterface {
            /**
             * {@inheritdoc}
             */
            public function preExecute(TaskInterface $task): void
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

        $workerMiddlewareStack = new FiberAwareWorkerMiddlewareStack(new WorkerMiddlewareStack(new MiddlewareRegistry([
            $middleware,
            $secondMiddleware,
            $erroredMiddleware,
        ])), $logger);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('An error occurred');
        self::expectExceptionCode(0);
        $workerMiddlewareStack->runPreExecutionMiddleware($task);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testStackCanRunEmptyPostMiddlewareList(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $middleware = $this->createMock(PreExecutionMiddlewareInterface::class);
        $middleware->expects(self::never())->method('preExecute')->with($task);

        $workerMiddlewareStack = new FiberAwareWorkerMiddlewareStack(new WorkerMiddlewareStack(new MiddlewareRegistry([
            $middleware,
        ])), $logger);

        $workerMiddlewareStack->runPostExecutionMiddleware($task, $worker);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testStackCanRunPostMiddlewareList(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $task = new NullTask('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $middleware = $this->createMock(PostExecutionMiddlewareInterface::class);
        $middleware->expects(self::once())->method('postExecute')->with($task);

        $secondMiddleware = $this->createMock(PreExecutionMiddlewareInterface::class);
        $secondMiddleware->expects(self::never())->method('preExecute')->with($task);

        $workerMiddlewareStack = new FiberAwareWorkerMiddlewareStack(new WorkerMiddlewareStack(new MiddlewareRegistry([
            $middleware,
            $secondMiddleware,
        ])), $logger);

        $workerMiddlewareStack->runPostExecutionMiddleware($task, $worker);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testStackCanReturnMiddlewareList(): void
    {
        $middleware = $this->createMock(PostExecutionMiddlewareInterface::class);
        $secondMiddleware = $this->createMock(PreExecutionMiddlewareInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $workerMiddlewareStack = new FiberAwareWorkerMiddlewareStack(new WorkerMiddlewareStack(new MiddlewareRegistry([
            $middleware,
            $secondMiddleware,
        ])), $logger);

        self::assertCount(2, $workerMiddlewareStack->getMiddlewareList());
    }
}
