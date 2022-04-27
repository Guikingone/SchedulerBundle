<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SingleRunTaskMiddlewareTest extends TestCase
{
    public function testMiddlewareIsConfigured(): void
    {
        $singleRunTaskMiddleware = new SingleRunTaskMiddleware(new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));

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

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware(new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), $logger);

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

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware(new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), $logger);

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

        $transport = new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($transport);

        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'single_run' => false,
        ]), $worker);
        self::assertCount(1, $transport->list());
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanHandleSingleRunTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $transport = new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($transport);

        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'single_run' => true,
        ]), $worker);
        self::assertSame(TaskInterface::PAUSED, $transport->get('foo')->getState());
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanHandleSingleRunTrueAndDeleteAfterExecuteTrueTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $transport = new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($transport);

        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'single_run' => true,
            'delete_after_execute' => true,
        ]), $worker);
        self::assertCount(0, $transport->list());
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanHandleSingleRunFalseAndDeleteAfterExecuteFalseTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $transport = new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($transport);

        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'single_run' => false,
            'delete_after_execute' => false,
        ]), $worker);
        self::assertCount(1, $transport->list());
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanHandleSingleRunFalseAndDeleteAfterExecuteTrueTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $transport = new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        $singleRunTaskMiddleware = new SingleRunTaskMiddleware($transport);

        $singleRunTaskMiddleware->postExecute(new NullTask('foo', [
            'single_run' => false,
            'delete_after_execute' => true,
        ]), $worker);
        self::assertCount(1, $transport->list());
    }
}
