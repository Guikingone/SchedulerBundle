<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

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

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCannotHandleTaskWithIncompleteExecutionState(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(self::equalTo('The task "foo" is marked as incomplete or to retry, the "is_single" option is not used'));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('pause');

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler, $logger);
        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'execution_state' => TaskInterface::INCOMPLETE,
        ]), $worker);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCannotHandleTaskWithToRetryExecutionState(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(self::equalTo('The task "foo" is marked as incomplete or to retry, the "is_single" option is not used'));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('pause');

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler, $logger);
        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'execution_state' => TaskInterface::TO_RETRY,
        ]), $worker);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCannotHandleInvalidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('pause');

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute($task, $worker);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanHandleSingleRunTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('isSingleRun')->willReturn(true);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('pause')->with(self::equalTo('foo'));

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute($task, $worker);
    }
}
