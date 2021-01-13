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
use SchedulerBundle\Runner\CallbackTaskRunner;
use SchedulerBundle\Task\CallbackTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallBackTaskRunnerTest extends TestCase
{
    public function testRunnerCannotSupportInvalidTask(): void
    {
        $runner = new CallbackTaskRunner();

        $task = new ShellTask('foo', ['echo', 'Symfony!']);
        static::assertFalse($runner->support($task));

        $task = new CallbackTask('foo', function () {
            return 1 + 1;
        });

        static::assertTrue($runner->support($task));
    }

    public function testRunnerCanExecuteValidTask(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function () {
            return 1 + 1;
        });

        $output = $runner->run($task);

        static::assertSame(TaskInterface::SUCCEED, $task->getExecutionState());
        static::assertSame('2', $output->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithCallable(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', [new FooCallable(), 'echo']);

        $output = $runner->run($task);

        static::assertSame(TaskInterface::SUCCEED, $task->getExecutionState());
        static::assertSame('Symfony', $runner->run($task)->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithArguments(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function ($a, $b) {
            return $a * $b;
        }, [1, 2]);

        $output = $runner->run($task);

        static::assertSame(TaskInterface::SUCCEED, $task->getExecutionState());
        static::assertSame('2', $runner->run($task)->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteInValidTask(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function ($a, $b) {
            return $a * $b;
        }, [1]);

        $output = $runner->run($task);

        static::assertSame(TaskInterface::ERRORED, $task->getExecutionState());
        static::assertNull($output->getOutput());
        static::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }
}

final class FooCallable
{
    public function echo(): string
    {
        return 'Symfony';
    }
}
