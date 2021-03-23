<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
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
        $list = new TaskList();

        self::assertEmpty($list);
    }

    public function testListCanBeCreatedWithTasks(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList([$task]);

        self::assertNotEmpty($list);
        self::assertSame(1, $list->count());
    }

    public function testListCanBeHydrated(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList();

        $task->expects(self::once())->method('getName')->willReturn('foo');
        $list->add($task);

        self::assertNotEmpty($list);
        self::assertSame(1, $list->count());
    }

    public function testListCanBeHydratedWithMultipleTasks(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $secondTask = $this->createMock(TaskInterface::class);

        $list = new TaskList();

        $task->expects(self::once())->method('getName')->willReturn('foo');
        $list->add($task, $secondTask);

        self::assertNotEmpty($list);
        self::assertSame(2, $list->count());
    }

    public function testListCannotBeHydratedUsingInvalidOffset(): void
    {
        $list = new TaskList();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('A task must be given, received "object"');
        self::expectExceptionCode(0);
        $list->offsetSet('foo', new stdClass());
    }

    public function testListCanBeHydratedUsingEmptyOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList();

        $task->expects(self::once())->method('getName')->willReturn('foo');
        $list->offsetSet(null, $task);

        self::assertNotEmpty($list);
        self::assertSame(1, $list->count());
    }

    public function testListCanBeHydratedUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList();

        $task->expects(self::any())->method('getName')->willReturn('foo');
        $list->offsetSet('foo', $task);

        self::assertNotEmpty($list);
        self::assertSame(1, $list->count());
    }

    public function testListHasTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        self::assertTrue($list->has('foo'));
    }

    public function testListHasTaskUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        self::assertTrue($list->offsetExists('foo'));
    }

    public function testListCannotReturnUndefinedTask(): void
    {
        $list = new TaskList([]);

        self::assertNull($list->get('foo'));
    }

    public function testListCanReturnTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        self::assertInstanceOf(TaskInterface::class, $list->get('foo'));
    }

    public function testListCanReturnTaskUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        self::assertInstanceOf(TaskInterface::class, $list->offsetGet('foo'));
    }

    public function testListCannotFindUndefinedTaskByNames(): void
    {
        $list = new TaskList([]);
        $tasks = $list->findByName(['foo']);

        self::assertEmpty($tasks);
    }

    public function testListCanFindTaskByNames(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::any())->method('getName')->willReturn('foo');

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::any())->method('getName')->willReturn('bar');

        $list = new TaskList([$task, $secondTask]);

        $tasks = $list->findByName(['foo']);

        self::assertNotEmpty($tasks);
        self::assertCount(1, $tasks);
    }

    public function testListCanFilterTaskByNames(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $list = new TaskList([$task]);

        $task->expects(self::any())->method('getName')->willReturn('foo');

        $tasks = $list->filter(fn (TaskInterface $task): bool => 'foo' === $task->getName());

        self::assertNotEmpty($tasks);
        self::assertInstanceOf(TaskList::class, $tasks);
        self::assertCount(1, $tasks);
    }

    public function testListCanRemoveTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        self::assertNotEmpty($list);
        self::assertSame(1, $list->count());

        $list->remove('foo');

        self::assertEmpty($list);
        self::assertSame(0, $list->count());
    }

    public function testListCanRemoveTaskUsingOffset(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        self::assertNotEmpty($list);
        self::assertSame(1, $list->count());

        $list->offsetUnset('foo');

        self::assertEmpty($list);
        self::assertSame(0, $list->count());
    }

    public function testIteratorCanBeReturned(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        self::assertNotEmpty($list->getIterator());
    }

    public function testArrayCanBeReturned(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        self::assertCount(1, $list->toArray());
    }

    public function testArrayCanBeReturnedWithoutKeys(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $list = new TaskList([$task]);

        self::assertCount(1, $list->toArray(false));
        self::assertArrayHasKey(0, $list->toArray(false));
        self::assertArrayNotHasKey('foo', $list->toArray(false));
    }
}
