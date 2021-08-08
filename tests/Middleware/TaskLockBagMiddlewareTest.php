<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\TaskLockBagMiddleware;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockBagMiddlewareTest extends TestCase
{
    public function testMiddlewareIsConfigured(): void
    {
        $middleware = new TaskLockBagMiddleware(new LockFactory(new InMemoryStore()));

        self::assertSame(5, $middleware->getPriority());
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCannotReleaseTaskAfterExecutionWithoutAccessLockBag(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects(self::never())->method('createLockFromKey');

        $middleware = new TaskLockBagMiddleware($lockFactory, $logger);

        self::expectException(RuntimeException::class);
        self::expectErrorMessage(sprintf('The task "foo" must be linked to an access lock bag, consider using %s::execute() or %s::schedule()', WorkerInterface::class, SchedulerInterface::class));
        self::expectExceptionCode(0);
        $middleware->postExecute(new NullTask('foo'), $worker);
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanReleaseTaskAfterExecution(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::equalTo('The lock for task "foo" has been released'));

        $lock = $this->createMock(LockInterface::class);
        $lock->expects(self::never())->method('acquire');
        $lock->expects(self::once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects(self::once())->method('createLockFromKey')->willReturn($lock);

        $task = new NullTask('foo');
        $task->setAccessLockBag(new AccessLockBag(new Key('foo')));
        self::assertInstanceOf(AccessLockBag::class, $task->getAccessLockBag());

        $middleware = new TaskLockBagMiddleware($lockFactory, $logger);
        $middleware->postExecute($task, $worker);

        self::assertNull($task->getAccessLockBag());
    }
}
