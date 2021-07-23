<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use stdClass;

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

    public function testListCannotBeHydratedUsingInvalidOffset(): void
    {
        $taskList = new TaskList();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('A task must be given, received "object"');
        self::expectExceptionCode(0);
        $taskList->offsetSet('foo', new stdClass());
    }

    public function testListCanBeHydratedUsingEmptyOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $taskList = new TaskList();

        $task->expects(self::once())->method('getName')->willReturn('foo');
        $taskList->offsetSet(null, $task);

        self::assertNotEmpty($taskList);
        self::assertSame(1, $taskList->count());
    }

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

        self::assertNull($taskList->get('foo'));
    }

    public function testListCanReturnTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = new TaskList([$task]);

        self::assertInstanceOf(TaskInterface::class, $taskList->get('foo'));
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

        $tasks = $taskList->filter(fn (TaskInterface $task): bool => 'foo' === $task->getName());

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
        $taskList->walk(fn (TaskInterface $task) => $task->addTag('walk'));

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
        ], $taskList->map(fn (TaskInterface $task): string => $task->getName()));
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

        $lastTask = $taskList->last();
        self::assertSame('bar', $lastTask->getName());
    }
}
