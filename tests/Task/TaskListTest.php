<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use stdClass;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskListTest extends TestCase
{
    public function testListCanBeCreatedWithEmptyTasks(): void
    {
        $taskList = new TaskList();

        self::assertCount(0, $taskList);
    }

    public function testListCanBeCreatedWithTasks(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $taskList = new TaskList([$task]);

        self::assertNotEmpty($taskList);
        self::assertSame(1, $taskList->count());
    }

    public function testListCanBeHydrated(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $taskList = new TaskList();

        $task->expects(self::once())->method('getName')->willReturn('foo');
        $taskList->add($task);

        self::assertNotEmpty($taskList);
        self::assertSame(1, $taskList->count());
    }

    public function testListCanBeHydratedWithMultipleTasks(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $secondTask = $this->createMock(TaskInterface::class);

        $taskList = new TaskList();

        $task->expects(self::once())->method('getName')->willReturn('foo');
        $taskList->add($task, $secondTask);

        self::assertNotEmpty($taskList);
        self::assertSame(2, $taskList->count());
    }

    /**
     * @throws Throwable {@see TaskListInterface::offsetSet()}
     */
    public function testListCannotBeHydratedUsingInvalidOffset(): void
    {
        $taskList = new TaskList();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('A task must be given, received "object"');
        self::expectExceptionCode(0);
        $taskList->offsetSet('foo', new stdClass());
    }

    /**
     * @throws Throwable {@see TaskListInterface::offsetSet()}
     */
    public function testListCanBeHydratedUsingEmptyOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $taskList = new TaskList();

        $task->expects(self::once())->method('getName')->willReturn('foo');
        $taskList->offsetSet(null, $task);

        self::assertNotEmpty($taskList);
        self::assertSame(1, $taskList->count());
    }

    /**
     * @throws Throwable {@see TaskListInterface::offsetSet()}
     */
    public function testListCanBeHydratedUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $taskList = new TaskList();

        $task->expects(self::any())->method('getName')->willReturn('foo');
        $taskList->offsetSet('foo', $task);

        self::assertNotEmpty($taskList);
        self::assertSame(1, $taskList->count());
    }

    public function testListHasTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = new TaskList([$task]);

        self::assertTrue($taskList->has('foo'));
    }

    public function testListHasTaskUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = new TaskList([$task]);

        self::assertTrue($taskList->offsetExists('foo'));
    }

    public function testListCannotReturnUndefinedTask(): void
    {
        $taskList = new TaskList([]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist or is invalid');
        self::expectExceptionCode(0);
        $taskList->get('foo');
    }

    public function testListCanReturnTask(): void
    {
        $taskList = new TaskList([
            new NullTask('foo'),
        ]);

        $task = $taskList->get('foo');
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testListCanReturnLazyTask(): void
    {
        $taskList = new TaskList([new NullTask('foo')]);

        $lazyTask = $taskList->get('foo', true);
        self::assertInstanceOf(LazyTask::class, $lazyTask);
        self::assertFalse($lazyTask->isInitialized());
        self::assertInstanceOf(NullTask::class, $lazyTask->getTask());
    }

    public function testListCanReturnTaskUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = new TaskList([$task]);

        self::assertInstanceOf(TaskInterface::class, $taskList->offsetGet('foo'));
    }

    public function testListCannotFindUndefinedTaskByNames(): void
    {
        $taskList = new TaskList([]);
        $tasks = $taskList->findByName(['foo']);

        self::assertCount(0, $tasks);
    }

    public function testListCanFindTaskByNames(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::any())->method('getName')->willReturn('foo');

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::any())->method('getName')->willReturn('bar');

        $taskList = new TaskList([$task, $secondTask]);

        $tasks = $taskList->findByName(['foo']);

        self::assertNotEmpty($tasks);
        self::assertCount(1, $tasks);
    }

    public function testListCanFilterTaskByNames(): void
    {
        $task = new NullTask('foo');
        $taskList = new TaskList([$task]);

        $tasks = $taskList->filter(static fn (TaskInterface $task): bool => 'foo' === $task->getName());

        self::assertCount(1, $tasks);
    }

    public function testListCanRemoveTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = new TaskList([$task]);

        self::assertNotEmpty($taskList);
        self::assertSame(1, $taskList->count());

        $taskList->remove('foo');

        self::assertCount(0, $taskList);
        self::assertSame(0, $taskList->count());
    }

    public function testListCanRemoveTaskUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = new TaskList([$task]);

        self::assertNotEmpty($taskList);
        self::assertSame(1, $taskList->count());

        $taskList->offsetUnset('foo');

        self::assertCount(0, $taskList);
        self::assertSame(0, $taskList->count());
    }

    public function testIteratorCanBeReturned(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = new TaskList([$task]);

        self::assertNotEmpty($taskList->getIterator());
    }

    public function testArrayCanBeReturned(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = new TaskList([$task]);

        self::assertCount(1, $taskList->toArray());
    }

    public function testArrayCanBeReturnedWithoutKeys(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = new TaskList([$task]);

        self::assertCount(1, $taskList->toArray(false));
        self::assertArrayHasKey(0, $taskList->toArray(false));
        self::assertArrayNotHasKey('foo', $taskList->toArray(false));
    }

    public function testListCanApplyClosureOnEachTask(): void
    {
        $nullTask = new NullTask('foo');

        self::assertCount(0, $nullTask->getTags());

        $taskList = new TaskList([$nullTask]);
        $taskList->walk(static fn (TaskInterface $task) => $task->addTag('walk'));

        self::assertCount(1, $nullTask->getTags());
        self::assertContains('walk', $nullTask->getTags());
    }

    public function testListCanApplyMapClosure(): void
    {
        $taskList = new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]);

        self::assertSame([
            'foo' => 'foo',
            'bar' => 'bar',
        ], $taskList->map(static fn (TaskInterface $task): string => $task->getName()));
    }

    public function testListCanApplyMapClosureWithoutKeys(): void
    {
        $taskList = new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]);

        self::assertSame([
            'foo',
            'bar',
        ], $taskList->map(static fn (TaskInterface $task): string => $task->getName(), false));
    }

    public function testListCannotReturnLastTaskWhileEmpty(): void
    {
        $taskList = new TaskList();

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The current list is empty');
        self::expectExceptionCode(0);
        $taskList->last();
    }

    public function testListCanReturnLastTask(): void
    {
        $taskList = new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]);

        self::assertCount(2, $taskList);

        $lastTask = $taskList->last();
        self::assertSame('bar', $lastTask->getName());
    }

    public function testListCanBeSorted(): void
    {
        $fooTask = new NullTask('foo');
        $fooTask->setScheduledAt(new DateTimeImmutable('- 1 month'));

        $barTask = new NullTask('bar');
        $barTask->setScheduledAt(new DateTimeImmutable('- 2 day'));

        $taskList = new TaskList([
            $fooTask,
            $barTask,
        ]);

        self::assertCount(2, $taskList);

        $taskList->uasort(static fn (TaskInterface $task, TaskInterface $nextTask): int => $task->getScheduledAt() <=> $nextTask->getScheduledAt());

        self::assertCount(2, $taskList);
        self::assertEquals([
            'foo' => $fooTask,
            'bar' => $barTask,
        ], $taskList->toArray());
    }

    public function testListCannotBeChunkedWithInvalidSize(): void
    {
        $taskList = new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The given size "0" cannot be used to split the list');
        self::expectExceptionCode(0);
        $taskList->chunk(0);
    }

    public function testListCanBeChunkedWithoutKeys(): void
    {
        $taskList = new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]);

        $chunk = $taskList->chunk(1);

        self::assertCount(2, $chunk);
        self::assertArrayHasKey(0, $chunk);
        self::assertIsArray($chunk[0]);
        self::assertCount(1, $chunk[0]);
        self::assertArrayHasKey(0, $chunk[0]);
        self::assertArrayHasKey(1, $chunk);
        self::assertIsArray($chunk[1]);
        self::assertCount(1, $chunk[1]);
        self::assertArrayHasKey(0, $chunk[1]);
    }

    public function testListCanBeChunkedWithKeys(): void
    {
        $taskList = new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]);

        $chunk = $taskList->chunk(1, true);

        self::assertCount(2, $chunk);
        self::assertArrayHasKey(0, $chunk);
        self::assertIsArray($chunk[0]);
        self::assertCount(1, $chunk[0]);
        self::assertArrayHasKey('foo', $chunk[0]);
        self::assertArrayHasKey(1, $chunk);
        self::assertIsArray($chunk[1]);
        self::assertCount(1, $chunk[1]);
        self::assertArrayHasKey('bar', $chunk[1]);
    }

    public function testListCannotSliceUndefinedKeys(): void
    {
        $taskList = new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The tasks cannot be found');
        self::expectExceptionCode(0);
        $taskList->slice('random');
    }

    public function testListCanSlice(): void
    {
        $list = new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]);

        self::assertCount(2, $list);

        $tasks = $list->slice('bar');

        self::assertCount(1, $list);
        self::assertCount(1, $tasks);
        self::assertFalse($tasks->has('foo'));
        self::assertTrue($list->has('foo'));
    }
}
