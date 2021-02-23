<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedTaskTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $chainedTask = new ChainedTask('foo', $task);

        self::assertSame('* * * * *', $chainedTask->getExpression());
        self::assertNotEmpty($chainedTask->getTasks());
        self::assertSame($task, $chainedTask->getTasks()[0]);
        self::assertSame($task, $chainedTask->getTask(0));
    }

    public function testAdditionalTaskCanBeAdded(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $chainedTask = new ChainedTask('foo');
        self::assertEmpty($chainedTask->getTasks());

        $chainedTask->addTask($task);

        self::assertNotEmpty($chainedTask->getTasks());
        self::assertSame($task, $chainedTask->getTasks()[0]);
        self::assertSame($task, $chainedTask->getTask(0));
    }

    public function testAdditionalTaskCanBeSet(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $chainedTask = new ChainedTask('foo');
        self::assertEmpty($chainedTask->getTasks());

        $chainedTask->setTasks($task);

        self::assertNotEmpty($chainedTask->getTasks());
        self::assertSame($task, $chainedTask->getTasks()[0]);
        self::assertSame($task, $chainedTask->getTask(0));
    }

    public function testAdditionalTaskCannotBeAccessedIfNotSet(): void
    {
        $chainedTask = new ChainedTask('foo');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task does not exist');
        self::expectExceptionCode(0);
        $chainedTask->getTask(0);
    }
}
