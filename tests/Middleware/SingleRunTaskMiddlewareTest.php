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
        $scheduler->expects(self::never())->method('unschedule');

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
        $scheduler->expects(self::never())->method('unschedule');

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

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('pause');
        $scheduler->expects(self::never())->method('unschedule');

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'single_run' => false,
        ]), $worker);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanHandleSingleRunTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('pause')->with(self::equalTo('foo'));
        $scheduler->expects(self::never())->method('unschedule');

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'single_run' => true,
        ]), $worker);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanHandleSingleRunTrueAndDeleteAfterExecuteTrueTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('pause');
        $scheduler->expects(self::once())->method('unschedule')->with(self::equalTo('foo'));

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'single_run' => true,
            'delete_after_execute' => true,
        ]), $worker);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanHandleSingleRunFalseAndDeleteAfterExecuteFalseTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('pause');
        $scheduler->expects(self::never())->method('unschedule');

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'single_run' => false,
            'delete_after_execute' => false,
        ]), $worker);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanHandleSingleRunFalseAndDeleteAfterExecuteTrueTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('pause');
        $scheduler->expects(self::never())->method('unschedule');

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($scheduler);
        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'single_run' => false,
            'delete_after_execute' => true,
        ]), $worker);
    }
}
