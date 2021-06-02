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

    public function testCommandCantBeCreatedWithInvalidCommand(): void
    {
        $commandTask = new CommandTask('test', 'cache:clear', [], ['--env' => 'test']);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The command argument must be a valid command FQCN|string, empty string given');
        self::expectExceptionCode(0);
        $commandTask->setCommand('');
    }

    public function testCommandCanBeCreatedWithoutArgumentsAndOptions(): void
    {
        $commandTask = new CommandTask('test', 'app:foo');

        self::assertEmpty($commandTask->getArguments());
        self::assertEmpty($commandTask->getOptions());
    }

    public function testCommandCanBeCreatedWithValidArguments(): void
    {
        $commandTask = new CommandTask('test', 'app:foo', ['test'], ['--env' => 'test']);

        self::assertSame('app:foo', $commandTask->getCommand());
        self::assertContainsEquals('test', $commandTask->getArguments());
        self::assertSame(['--env' => 'test'], $commandTask->getOptions());
    }
}
