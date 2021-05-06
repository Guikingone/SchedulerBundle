<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\TaskLockBagMiddleware;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\TaskBag\LockTaskBag;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockBagMiddlewareTest extends TestCase
{
    /**
     * @throws Throwable {@see PostSchedulingMiddlewareInterface::postScheduling()}
     */
    public function testMiddlewareCannotBeCalledIfBagAlreadyExist(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('update');

        $task = new NullTask('foo');
        $task->setExecutionLockBag(new LockTaskBag());

        $middleware = new TaskLockBagMiddleware(new LockFactory(new FlockStore()), $logger);
        $middleware->postScheduling($task, $scheduler);
    }

    public function testMiddlewareCannotStoreKeyIfTheFactoryDoesNotSupportIt(): void
    {
    }
}
