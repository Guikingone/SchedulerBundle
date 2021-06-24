<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTaskListTest extends TestCase
{
    public function testListCanBeInitialized(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertCount(0, $list);
    }

    public function testListCanReceiveTask(): void
    {
        $list = new LazyTaskList(new TaskList());
        $list->add(new NullTask('foo'));

        self::assertCount(1, $list);
    }

    public function testListCanCheckTaskExistence(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertFalse($list->has('foo'));

        $list->add(new NullTask('foo'));
        self::assertTrue($list->has('foo'));
    }

    public function testListCanReturnTask(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertNull($list->get('foo'));

        $list->add(new NullTask('foo'));
        self::assertInstanceOf(NullTask::class, $list->get('foo'));
    }

    public function testListCanReturnTaskLazily(): void
    {
        $list = new LazyTaskList(new TaskList());
        self::assertNull($list->get('foo'));

        $list->add(new NullTask('foo'));

        $lazyTask = $list->get('foo', true);
        self::assertInstanceOf(LazyTask::class, $lazyTask);
        self::assertFalse($lazyTask->isInitialized());

        $task = $lazyTask->getTask();
        self::assertTrue($lazyTask->isInitialized());
        self::assertInstanceOf(NullTask::class, $task);
    }

    public function testListCanFindTaskByName(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertCount(0, $list->findByName(['foo']));

        $list->add(new NullTask('foo'));
        $filteredLazyList = $list->findByName(['foo']);

        self::assertTrue($filteredLazyList->isInitialized());
        self::assertCount(1, $filteredLazyList);
    }

    public function testListCanFilterTask(): void
    {
        $lazyList = new LazyTaskList(new TaskList());

        self::assertFalse($lazyList->isInitialized());
        self::assertCount(0, $lazyList->filter(fn (TaskInterface $task): bool => $task->getExpression() === '@reboot'));

        $lazyList->add(new NullTask('foo'));
        $filteredLazyList = $lazyList->filter(fn (TaskInterface $task): bool => $task->getExpression() === '* * * * *');

        self::assertTrue($filteredLazyList->isInitialized());
        self::assertCount(1, $filteredLazyList);
    }

    public function testListCanRemoveTask(): void
    {
        $list = new LazyTaskList(new TaskList());
        $list->remove('foo');

        self::assertCount(0, $list);

        $list->add(new NullTask('foo'));
        self::assertCount(1, $list);

        $list->remove('foo');
        self::assertCount(0, $list);
    }

    public function testListCannotWalkThroughEmptyList(): void
    {
        $list = new LazyTaskList(new TaskList());
        self::assertCount(0, $list);

        $list->walk(function (TaskInterface $task): void {
            $task->addTag('walk');
        });
        self::assertCount(0, $list);

        $list->walk(function (TaskInterface $task): void {
            $task->addTag('walk');
        });
        self::assertCount(0, $list);
    }

    public function testListCanWalkThroughTask(): void
    {
        $list = new LazyTaskList(new TaskList());

        $list->add(new NullTask('foo'));

        $task = $list->get('foo');
        self::assertInstanceOf(TaskInterface::class, $task);
        self::assertCount(0, $task->getTags());

        $list->walk(function (TaskInterface $task): void {
            $task->addTag('walk');
        });

        $task = $list->get('foo');
        self::assertInstanceOf(TaskInterface::class, $task);
        self::assertCount(1, $task->getTags());
    }

    public function testInitializedListCanApplyMapClosure(): void
    {
        $list = new LazyTaskList(new TaskList());

        $list->add(new NullTask('foo'));
        $list->add(new NullTask('bar'));

        self::assertTrue($list->isInitialized());
        self::assertSame(['foo' => 'foo', 'bar' => 'bar'], $list->map(fn (TaskInterface $task): string => $task->getName()));
    }

    public function testNotInitializedListCanApplyMapClosure(): void
    {
        $list = new LazyTaskList(new TaskList([new NullTask('foo'), new NullTask('bar')]));

        self::assertFalse($list->isInitialized());
        self::assertSame(['foo' => 'foo', 'bar' => 'bar'], $list->map(fn (TaskInterface $task): string => $task->getName()));
    }

    public function testListCanReturnEmptyListAsArray(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertEquals([], $list->toArray(false));
        self::assertEquals([], $list->toArray());
    }

    public function testListCanReturnTasksAsArray(): void
    {
        $task = new NullTask('foo');

        $list = new LazyTaskList(new TaskList());
        $list->add($task);

        self::assertEquals([$task], $list->toArray(false));
        self::assertEquals(['foo' => $task], $list->toArray());
    }

    public function testListCanCheckOffsetExistence(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertFalse($list->offsetExists('foo'));

        $list->add(new NullTask('foo'));
        self::assertTrue($list->offsetExists('foo'));
    }

    public function testListCanSetOffsetWithoutBeingInitialized(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertCount(0, $list);

        $list->offsetSet('foo', new NullTask('foo'));
        self::assertCount(1, $list);
    }

    public function testListCanSetOffset(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertCount(0, $list);
        self::assertNull($list->get('foo'));

        $list->offsetSet('foo', new NullTask('foo'));
        self::assertCount(1, $list);
        self::assertInstanceOf(NullTask::class, $list->get('foo'));
    }

    public function testListCannotGetNullOffset(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertCount(0, $list);
        self::assertNull($list->offsetGet('foo'));
    }

    public function testListCanGetOffset(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertCount(0, $list);

        $list->add(new NullTask('foo'));
        self::assertInstanceOf(NullTask::class, $list->offsetGet('foo'));
    }

    public function testListCanGetOffsetOnTaskList(): void
    {
        $list = new LazyTaskList(new TaskList([
            new NullTask('foo'),
        ]));

        self::assertCount(1, $list);
        self::assertInstanceOf(NullTask::class, $list->offsetGet('foo'));
    }

    public function testListCanUnsetOffsetWithoutBeingInitialized(): void
    {
        $list = new LazyTaskList(new TaskList());

        $list->offsetUnset('foo');
        self::assertCount(0, $list);
    }

    public function testListCanUnsetOffset(): void
    {
        $list = new LazyTaskList(new TaskList());

        $list->add(new NullTask('foo'));
        self::assertCount(1, $list);
        self::assertInstanceOf(NullTask::class, $list->get('foo'));

        $list->offsetUnset('foo');
        self::assertCount(0, $list);
    }

    public function testListCannotReturnLastTaskWhileEmpty(): void
    {
        $taskList = new LazyTaskList(new TaskList());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The current list is empty');
        self::expectExceptionCode(0);
        $taskList->last();
    }

    public function testListCanReturnLastTask(): void
    {
        $taskList = new LazyTaskList(new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]));

        self::assertFalse($taskList->isInitialized());

        $lastTask = $taskList->last();
        self::assertTrue($taskList->isInitialized());
        self::assertSame('bar', $lastTask->getName());
    }
}
