<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTaskTest extends TestCase
{
    public function testTaskCanBeCreatedWithValidInformations(): void
    {
        self::assertSame('foo', (new NullTask('foo'))->getName());
        self::assertSame('* * * * *', (new NullTask('foo'))->getExpression());
        self::assertFalse((new NullTask('foo'))->mustRunInBackground());
        self::assertNull((new NullTask('foo'))->getDescription());
        self::assertSame(TaskInterface::ENABLED, (new NullTask('foo'))->getState());
    }

    public function testTaskCanBeCreatedWithBackgroundOption(): void
    {
        $task = new NullTask('foo');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(\sprintf('The background option is available only for task of type %s', ShellTask::class));
        $task->setBackground(true);
    }

    /**
     * @dataProvider provideNice
     */
    public function testTaskCannotBeCreatedWithInvalidNice(int $nice): void
    {
        $task = new NullTask('foo');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The nice value is not valid');
        $task->setNice($nice);
    }

    public function testTaskCannotBeCreatedWithPreviousDate(): void
    {
        $task = new NullTask('foo');

        self::expectException(LogicException::class);
        self::expectExceptionMessage('The date cannot be previous to the current date');
        $task->setExecutionStartDate('- 10 minutes');
    }

    public function testTaskCanBeCreatedWithDate(): void
    {
        $task = new NullTask('foo');
        $task->setExecutionStartDate('+ 10 minutes');
        $task->setExecutionEndDate('+ 20 minutes');

        self::assertInstanceOf(\DateTimeImmutable::class, $task->getExecutionStartDate());
        self::assertInstanceOf(\DateTimeImmutable::class, $task->getExecutionEndDate());
    }

    public function provideNice(): \Generator
    {
        yield [20];
        yield [-25];
        yield [200];
    }
}
