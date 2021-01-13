<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTaskTest extends TestCase
{
    public function testTaskCanBeCreatedWithValidInformations(): void
    {
        static::assertSame('foo', (new NullTask('foo'))->getName());
    }

    public function testTaskCanBeCreatedWithBackgroundOption(): void
    {
        $task = new NullTask('foo');

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage(sprintf('The background option is available only for task of type %s', ShellTask::class));
        $task->setBackground(true);
    }

    /**
     * @dataProvider provideNice
     */
    public function testTaskCannotBeCreatedWithInvalidNice(int $nice): void
    {
        $task = new NullTask('foo');

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The nice value is not valid');
        $task->setNice($nice);
    }

    public function testTaskCannotBeCreatedWithPreviousDate(): void
    {
        $task = new NullTask('foo');

        static::expectException(LogicException::class);
        static::expectExceptionMessage('The date cannot be previous to the current date');
        $task->setExecutionStartDate('- 10 minutes');
    }

    public function testTaskCanBeCreatedWithDate(): void
    {
        $task = new NullTask('foo');
        $task->setExecutionStartDate('+ 10 minutes');
        $task->setExecutionEndDate('+ 20 minutes');

        static::assertInstanceOf(\DateTimeImmutable::class, $task->getExecutionStartDate());
        static::assertInstanceOf(\DateTimeImmutable::class, $task->getExecutionEndDate());
    }

    public function provideNice(): \Generator
    {
        yield [20];
        yield [-25];
        yield [200];
    }
}
