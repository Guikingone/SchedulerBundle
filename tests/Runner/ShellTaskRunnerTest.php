<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Runner\ShellTaskRunner;
use SchedulerBundle\Task\AbstractTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTaskRunnerTest extends TestCase
{
    public function testRunnerCantSupportWrongTask(): void
    {
        $task = new FooTask('test');

        $runner = new ShellTaskRunner();

        static::assertFalse($runner->support($task));
        static::assertTrue($runner->support(new ShellTask('test', ['echo', 'Symfony'])));
    }

    public function testRunnerCanSupportValidTaskWithoutOutput(): void
    {
        $task = new ShellTask('test', ['echo', 'Symfony']);
        $task->setEnvironmentVariables(['env' => 'test']);
        $task->setTimeout(10);

        $runner = new ShellTaskRunner();
        static::assertTrue($runner->support($task));
        static::assertNull($runner->run($task)->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $runner->run($task)->getTask()->getExecutionState());
    }

    public function testRunnerCanSupportValidTaskWithOutput(): void
    {
        $task = new ShellTask('test', ['echo', 'Symfony']);
        $task->setEnvironmentVariables(['env' => 'test']);
        $task->setOutput(true);

        $runner = new ShellTaskRunner();
        static::assertTrue($runner->support($task));
        static::assertSame('Symfony', $runner->run($task)->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $runner->run($task)->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnEmptyOutputOnBackgroundTask(): void
    {
        $task = new ShellTask('test', ['echo', 'Symfony']);
        $task->setEnvironmentVariables(['env' => 'test']);
        $task->setOutput(true);
        $task->setBackground(true);

        $runner = new ShellTaskRunner();
        static::assertTrue($runner->support($task));
        static::assertSame('Task is running in background, output is not available', $runner->run($task)->getOutput());
        static::assertSame(TaskInterface::INCOMPLETE, $runner->run($task)->getTask()->getExecutionState());
    }
}

final class FooTask extends AbstractTask
{
}
