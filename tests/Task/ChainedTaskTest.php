<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedTaskTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $chainedTask = new ChainedTask('foo', $task);

        self::assertSame('* * * * *', $chainedTask->getExpression());
        self::assertNotEmpty($chainedTask->getTasks());
        self::assertSame($task, $chainedTask->getTasks()['foo']);
        self::assertSame($task, $chainedTask->getTask('foo'));
    }

    public function testAdditionalTaskCanBeAdded(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $chainedTask = new ChainedTask('foo');
        self::assertCount(0, $chainedTask->getTasks());

        $chainedTask->addTask($task);

        self::assertCount(1, $chainedTask->getTasks());
        self::assertSame($task, $chainedTask->getTasks()['foo']);
        self::assertSame($task, $chainedTask->getTask('foo'));
    }

    public function testAdditionalTaskCanBeSet(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $chainedTask = new ChainedTask('foo');
        self::assertCount(0, $chainedTask->getTasks());

        $chainedTask->setTasks(new TaskList([$task]));

        self::assertCount(1, $chainedTask->getTasks());
        self::assertSame($task, $chainedTask->getTasks()['foo']);
        self::assertSame($task, $chainedTask->getTask('foo'));
    }

    public function testAdditionalTaskCannotBeAccessedIfNotSet(): void
    {
        $chainedTask = new ChainedTask('foo');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist or is invalid');
        self::expectExceptionCode(0);
        $chainedTask->getTask('foo');
    }
}
