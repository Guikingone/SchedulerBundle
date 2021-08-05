<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\PostWorkerStartMiddlewareInterface;
use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\TaskLockBagMiddleware;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskLockRegistry;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\TaskBag\ExecutionLockBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockBagMiddlewareTest extends TestCase
{
    public function testMiddlewareIsConfigured(): void
    {
        $middleware = new TaskLockBagMiddleware(new LockFactory(new FlockStore()), new TaskLockRegistry());

        self::assertInstanceOf(PreSchedulingMiddlewareInterface::class, $middleware);
        self::assertInstanceOf(PostWorkerStartMiddlewareInterface::class, $middleware);
        self::assertInstanceOf(PostExecutionMiddlewareInterface::class, $middleware);
    }

    /**
     * @throws Throwable {@see PostSchedulingMiddlewareInterface::postScheduling()}
     */
    public function testMiddlewareCannotBeCalledIfBagAlreadyExist(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The task "foo" has already an access lock bag'));

        $task = new NullTask('foo');
        $task->setAccessLockBag(new AccessLockBag(new Key('foo')));

        $middleware = new TaskLockBagMiddleware(new LockFactory(new FlockStore()), new TaskLockRegistry(), $logger);
        $middleware->preScheduling($task, $scheduler);

        self::assertInstanceOf(AccessLockBag::class, $task->getAccessLockBag());
    }

    /**
     * @throws Throwable {@see PostSchedulingMiddlewareInterface::postScheduling()}
     */
    public function testMiddlewareCannotStoreKeyIfTheStoreCannotAcquireIt(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The lock related to the task "foo" cannot be acquired, it will be created before executing the task'));

        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('acquire')->with(self::equalTo(false))->willReturn(false);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects(self::once())->method('createLockFromKey')->willReturn($lock);

        $middleware = new TaskLockBagMiddleware($lockFactory, new TaskLockRegistry(), $logger);
        $middleware->preScheduling(new NullTask('foo'), $scheduler);
    }

    /**
     * @throws Throwable {@see PostSchedulingMiddlewareInterface::postScheduling()}
     */
    public function testMiddlewareCanStoreKeyIfTheStoreCanAcquireIt(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('acquire')->with(self::equalTo(false))->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects(self::once())->method('createLockFromKey')->willReturn($lock);

        $task = new NullTask('foo');

        $middleware = new TaskLockBagMiddleware($lockFactory, new TaskLockRegistry(), $logger);
        $middleware->preScheduling($task, $scheduler);

        $executionLockBag = $task->getExecutionLockBag();
        self::assertInstanceOf(ExecutionLockBag::class, $executionLockBag);

        $key = $executionLockBag->getLock();
        self::assertInstanceOf(Key::class, $executionLockBag->getLock());
        self::assertSame('_symfony_scheduler__foo_', (string) $key);
    }

    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     */
    public function testMiddlewareCanCreateLockBagBeforeExecutionIfUndefined(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('An execution lock bag has been created for task "foo"'));

        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('acquire')->with(self::equalTo(true))->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects(self::once())->method('createLockFromKey')->willReturn($lock);

        $task = new NullTask('foo');

        $middleware = new TaskLockBagMiddleware($lockFactory, new TaskLockRegistry(), $logger);
        $middleware->preExecute($task);

        self::assertInstanceOf(ExecutionLockBag::class, $task->getExecutionLockBag());
    }

    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     */
    public function testMiddlewareCanCreateLockBagBeforeExecutionIfKeyIsNotSet(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::once())->method('acquire')->with(self::equalTo(true))->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects(self::once())->method('createLockFromKey')->willReturn($lock);

        $lockTaskBag = new ExecutionLockBag();

        $task = new NullTask('foo');
        $task->setExecutionLockBag($lockTaskBag);

        $middleware = new TaskLockBagMiddleware($lockFactory, new TaskLockRegistry(), $logger);
        $middleware->preExecute($task);

        self::assertNotSame($lockTaskBag, $task->getExecutionLockBag());
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanReleaseTaskAfterExecution(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The lock for task "foo" has been released'));

        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::never())->method('acquire');
        $lock->expects(self::once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects(self::once())->method('createLockFromKey')->willReturn($lock);

        $task = new NullTask('foo');
        $task->setExecutionLockBag(new ExecutionLockBag(new Key('foo')));

        $middleware = new TaskLockBagMiddleware($lockFactory, $logger);
        $middleware->postExecute($task);
    }
}
