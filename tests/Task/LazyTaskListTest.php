<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
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
        self::assertFalse($list->isInitialized());

        self::assertCount(0, $list);
    }

    public function testListCanReceiveTask(): void
    {
        $list = new LazyTaskList(new TaskList());
        self::assertFalse($list->isInitialized());

        $list->add(new NullTask('foo'));
        self::assertCount(1, $list);
    }

    public function testListCanCheckTaskExistence(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertFalse($list->isInitialized());
        self::assertFalse($list->has('foo'));

        $list->add(new NullTask('foo'));
        self::assertTrue($list->has('foo'));
    }

    public function testListCanReturnTask(): void
    {
        $list = new LazyTaskList(new TaskList());
        self::assertFalse($list->isInitialized());

        $list->add(new NullTask('foo'));
        self::assertTrue($list->isInitialized());
        self::assertInstanceOf(NullTask::class, $list->get('foo'));

        self::assertTrue($list->isInitialized());
        self::assertInstanceOf(NullTask::class, $list->get('foo'));
    }

    public function testListCanReturnTaskLazily(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertFalse($list->isInitialized());
        $list->add(new NullTask('foo'));

        $lazyTask = $list->get('foo', true);
        self::assertTrue($list->isInitialized());
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
        self::assertCount(0, $task->getTags());

        $list->walk(function (TaskInterface $task): void {
            $task->addTag('walk');
        });

        $task = $list->get('foo');
        self::assertCount(1, $task->getTags());
    }

    public function testInitializedListCanApplyMapClosure(): void
    {
        $lazyTaskList = new LazyTaskList(new TaskList());

        $lazyTaskList->add(new NullTask('foo'));
        $lazyTaskList->add(new NullTask('bar'));

        self::assertTrue($lazyTaskList->isInitialized());
        self::assertSame(['foo' => 'foo', 'bar' => 'bar'], $lazyTaskList->map(fn (TaskInterface $task): string => $task->getName()));
    }

    public function testNotInitializedListCanApplyMapClosure(): void
    {
        $lazyTaskList = new LazyTaskList(new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]));

        self::assertFalse($lazyTaskList->isInitialized());
        self::assertSame(['foo' => 'foo', 'bar' => 'bar'], $lazyTaskList->map(fn (TaskInterface $task): string => $task->getName()));
    }

    public function testListCanApplyMapClosureWithoutKeys(): void
    {
        $lazyTaskList = new LazyTaskList(new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]));

        self::assertFalse($lazyTaskList->isInitialized());
        self::assertSame([
            'foo',
            'bar',
        ], $lazyTaskList->map(static fn (TaskInterface $task): string => $task->getName(), false));
        self::assertTrue($lazyTaskList->isInitialized());
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

        $list->offsetSet('foo', new NullTask('foo'));
        self::assertCount(1, $list);
        self::assertInstanceOf(NullTask::class, $list->get('foo'));
    }

    public function testListCannotGetNullOffset(): void
    {
        $list = new LazyTaskList(new TaskList());

        self::assertCount(0, $list);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist or is invalid');
        self::expectExceptionCode(0);
        $list->offsetGet('foo');
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

    public function testListCanBeSorted(): void
    {
        $fooTask = new NullTask('foo');
        $fooTask->setScheduledAt(new DateTimeImmutable('- 1 month'));

        $barTask = new NullTask('bar');
        $barTask->setScheduledAt(new DateTimeImmutable('- 2 day'));

        $taskList = new LazyTaskList(new TaskList([
            $fooTask,
            $barTask,
        ]));

        self::assertFalse($taskList->isInitialized());
        self::assertCount(2, $taskList);

        $taskList->uasort(fn (TaskInterface $task, TaskInterface $nextTask): int => $task->getScheduledAt() <=> $nextTask->getScheduledAt());

        self::assertTrue($taskList->isInitialized());
        self::assertCount(2, $taskList);
        self::assertEquals([
            'foo' => $fooTask,
            'bar' => $barTask,
        ], $taskList->toArray());

        $taskList->uasort(fn (TaskInterface $task, TaskInterface $nextTask): int => $nextTask->getScheduledAt() <=> $task->getScheduledAt());

        self::assertTrue($taskList->isInitialized());
        self::assertCount(2, $taskList);
        self::assertEquals([
            'bar' => $barTask,
            'foo' => $fooTask,
        ], $taskList->toArray());
    }

    public function testListCannotBeChunkedWithInvalidSize(): void
    {
        $list = new LazyTaskList(new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]));
        self::assertFalse($list->isInitialized());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The given size "0" cannot be used to split the list');
        self::expectExceptionCode(0);
        $list->chunk(0);
    }

    public function testListCanBeChunkedWithoutKeys(): void
    {
        $list = new LazyTaskList(new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]));
        self::assertFalse($list->isInitialized());

        $chunk = $list->chunk(1);

        self::assertTrue($list->isInitialized());
        self::assertCount(2, $chunk);
        self::assertCount(1, $chunk[0]);
        self::assertArrayHasKey(0, $chunk[0]);
        self::assertCount(1, $chunk[1]);
        self::assertArrayHasKey(0, $chunk[1]);
    }

    public function testListCanBeChunkedWithKeys(): void
    {
        $list = new LazyTaskList(new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]));
        self::assertFalse($list->isInitialized());

        $chunk = $list->chunk(1, true);

        self::assertTrue($list->isInitialized());
        self::assertCount(2, $chunk);
        self::assertCount(1, $chunk[0]);
        self::assertArrayHasKey('foo', $chunk[0]);
        self::assertCount(1, $chunk[1]);
        self::assertArrayHasKey('bar', $chunk[1]);
    }

    public function testListCannotSliceUndefinedKeys(): void
    {
        $list = new LazyTaskList(new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]));
        self::assertFalse($list->isInitialized());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The tasks cannot be found');
        self::expectExceptionCode(0);
        $list->slice('random');
    }

    public function testListCanSlice(): void
    {
        $list = new LazyTaskList(new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]));

        self::assertFalse($list->isInitialized());
        self::assertCount(2, $list);

        $tasks = $list->slice('bar');

        self::assertTrue($list->isInitialized());
        self::assertCount(1, $list);
        self::assertCount(1, $tasks);
        self::assertFalse($tasks->has('foo'));
        self::assertTrue($list->has('foo'));
    }
}
