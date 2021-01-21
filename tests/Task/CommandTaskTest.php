<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\CommandTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTaskTest extends TestCase
{
    public function testCommandCantBeCreatedWithInvalidArguments(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The command argument must be a valid command FQCN|string, empty string given');
        self::expectExceptionCode(0);
        new CommandTask('test', '', [], ['--env' => 'test']);
    }

    public function testCommandCanBeCreatedWithValidArguments(): void
    {
        $task = new CommandTask('test', 'app:foo', ['test'], ['--env' => 'test']);

        self::assertSame('app:foo', $task->getCommand());
        self::assertContainsEquals('test', $task->getArguments());
        self::assertSame(['--env' => 'test'], $task->getOptions());
    }
}
