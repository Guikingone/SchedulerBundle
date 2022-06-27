<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\LockedTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskListInterface;
use stdClass;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractTaskListTest extends TestCase
{
    abstract public function getTaskList(): TaskListInterface|LockedTaskList|LazyTaskList;

    public function testListCanBeCreatedWithEmptyTasks(): void
    {
        $taskList = $this->getTaskList();

        self::assertCount(expectedCount: 0, haystack: $taskList);
    }

    public function testListCanBeHydrated(): void
    {
        $taskList = $this->getTaskList();
        $taskList->add(task: new NullTask(name: 'foo'));

        self::assertNotEmpty(actual: $taskList);
        self::assertSame(expected: 1, actual: $taskList->count());
    }

    public function testListCanBeHydratedWithMultipleTasks(): void
    {
        $taskList = $this->getTaskList();

        $taskList->add(
            new NullTask(name: 'foo'),
            new NullTask(name: 'bar')
        );

        self::assertNotEmpty(actual: $taskList);
        self::assertSame(expected: 2, actual: $taskList->count());
    }

    /**
     * @throws Throwable {@see TaskListInterface::offsetSet()}
     */
    public function testListCannotBeHydratedUsingInvalidOffset(): void
    {
        $taskList = $this->getTaskList();

        self::expectException(exception: InvalidArgumentException::class);
        self::expectExceptionMessage(message: 'A task must be given, received "object"');
        self::expectExceptionCode(code: 0);
        $taskList->offsetSet(offset: 'foo', value: new stdClass());
    }
}
