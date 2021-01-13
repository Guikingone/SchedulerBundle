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
use SchedulerBundle\Task\CommandTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTaskTest extends TestCase
{
    public function testCommandCantBeCreatedWithInvalidArguments(): void
    {
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The command argument must be a valid command FQCN|string, empty string given');
        new CommandTask('test', '', [], ['--env' => 'test']);
    }

    public function testCommandCanBeCreatedWithValidArguments(): void
    {
        $task = new CommandTask('test', 'app:foo', ['test'], ['--env' => 'test']);

        static::assertSame('app:foo', $task->getCommand());
        static::assertContainsEquals('test', $task->getArguments());
        static::assertSame(['--env' => 'test'], $task->getOptions());
    }
}
