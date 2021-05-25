<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTaskListTest extends TestCase
{
    public function testListCanBeInitialized(): void
    {
        $list = new LazyTaskList();

        self::assertCount(0, $list);
    }

    public function testListCanReceiveTask(): void
    {
        $list = new LazyTaskList();
        $list->add(new NullTask('foo'));

        self::assertCount(1, $list);
    }

    public function testListCanCheckTaskExistence(): void
    {
        $list = new LazyTaskList();

        self::assertFalse($list->has('foo'));

        $list->add(new NullTask('foo'));
        self::assertTrue($list->has('foo'));
    }

    public function testListCanReturnTask(): void
    {
        $list = new LazyTaskList();

        self::assertNull($list->get('foo'));

        $list->add(new NullTask('foo'));
        self::assertInstanceOf(NullTask::class, $list->get('foo'));
    }

    public function testListCanFindTaskByName(): void
    {
        $list = new LazyTaskList();

        self::assertCount(0, $list->findByName(['foo']));

        $list->add(new NullTask('foo'));
        self::assertCount(1, $list->findByName(['foo']));
    }

    public function testListCanFilterTask(): void
    {
        $list = new LazyTaskList();

        self::assertCount(0, $list->filter(fn (TaskInterface $task): bool => $task->getExpression() === '@reboot'));

        $list->add(new NullTask('foo'));
        self::assertCount(1, $list->filter(fn (TaskInterface $task): bool => $task->getExpression() === '* * * * *'));
    }

    public function testListCanRemoveTask(): void
    {
        $list = new LazyTaskList();
        $list->remove('foo');

        self::assertCount(0, $list);

        $list->add(new NullTask('foo'));
        self::assertCount(1, $list);

        $list->remove('foo');
        self::assertCount(0, $list);
    }

    public function testListCanWalkThroughTask(): void
    {
        $list = new LazyTaskList();
        $list->add(new NullTask('foo'));
        self::assertCount(0, $list->get('foo')->getTags());

        $list->walk(function (TaskInterface $task): void {
            $task->addTag('walk');
        });
        self::assertCount(1, $list->get('foo')->getTags());
    }

    public function testListCanReturnTasksAsArray(): void
    {
        $task = new NullTask('foo');

        $list = new LazyTaskList();
        $list->add($task);

        self::assertEquals([$task], $list->toArray(false));
        self::assertEquals(['foo' => $task], $list->toArray());
    }

    public function testListCanCheckOffsetExistence(): void
    {
        $list = new LazyTaskList();

        self::assertFalse($list->offsetExists('foo'));

        $list->add(new NullTask('foo'));
        self::assertTrue($list->offsetExists('foo'));
    }

    public function testListCanSetOffset(): void
    {
        $list = new LazyTaskList();

        self::assertCount(0, $list);

        $list->offsetSet('foo', new NullTask('foo'));
        self::assertCount(1, $list);
    }

    public function testListCanGetOffset(): void
    {
        $list = new LazyTaskList();

        self::assertCount(0, $list);

        $list->offsetSet('foo', new NullTask('foo'));
        self::assertInstanceOf(NullTask::class, $list->offsetGet('foo'));
    }

    public function testListCanUnsetOffset(): void
    {
        $list = new LazyTaskList();
        $list->offsetUnset('foo');

        self::assertCount(0, $list);

        $list->add(new NullTask('foo'));
        self::assertCount(1, $list);

        $list->offsetUnset('foo');
        self::assertCount(0, $list);
    }
}
