<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTaskTest extends TestCase
{
    public function testTaskCanReturnEmbeddedTask(): void
    {
        $lazyTask = new LazyTask('foo', fn (): TaskInterface => new NullTask('foo'));

        self::assertSame('foo.lazy', $lazyTask->getName());
        self::assertFalse($lazyTask->isInitialized());

        $task = $lazyTask->getTask();
        self::assertTrue($lazyTask->isInitialized());
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
    }
}
