<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Runner\CallbackTaskRunner;
use SchedulerBundle\Task\CallbackTask;
use SchedulerBundle\Task\Output;
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
        self::assertFalse($runner->support($task));

        $task = new CallbackTask('foo', function (): int {
            return 1 + 1;
        });

        self::assertTrue($runner->support($task));
    }

    public function testRunnerCannotExecuteInvalidTask(): void
    {
        $runner = new CallbackTaskRunner();
        $secondTask = new ShellTask('foo', ['echo', 'Symfony!']);

        $output = $runner->run($secondTask);
        self::assertSame(Output::ERROR, $output->getType());
        self::assertSame(TaskInterface::ERRORED, $secondTask->getExecutionState());
        self::assertNull($output->getOutput());
        self::assertSame($secondTask, $output->getTask());
    }

    public function testRunnerCanExecuteValidTaskWithExtraSpacedOutput(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function (): string {
            return '   hello';
        });

        $output = $runner->run($task);

        self::assertSame(TaskInterface::SUCCEED, $task->getExecutionState());
        self::assertSame('hello', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTask(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function (): int {
            return 1 + 1;
        });

        $output = $runner->run($task);

        self::assertSame(TaskInterface::SUCCEED, $task->getExecutionState());
        self::assertSame('2', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithCallable(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', [new FooCallable(), 'echo']);

        $output = $runner->run($task);

        self::assertSame(TaskInterface::SUCCEED, $task->getExecutionState());
        self::assertSame('Symfony', $runner->run($task)->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithArguments(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function ($a, $b): int {
            return $a * $b;
        }, [1, 2]);

        $output = $runner->run($task);

        self::assertSame(TaskInterface::SUCCEED, $task->getExecutionState());
        self::assertSame('2', $runner->run($task)->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteInvalidTask(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function ($a, $b): int {
            return $a * $b;
        }, [1]);

        $output = $runner->run($task);

        self::assertSame(TaskInterface::ERRORED, $task->getExecutionState());
        self::assertNull($output->getOutput());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteTaskWithFalseReturn(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function (): bool {
            return false;
        });

        $output = $runner->run($task);

        self::assertSame(TaskInterface::ERRORED, $task->getExecutionState());
        self::assertNull($output->getOutput());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteTaskWithTrueReturn(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function (): bool {
            return true;
        });

        $output = $runner->run($task);

        self::assertSame(TaskInterface::SUCCEED, $task->getExecutionState());
        self::assertSame('1', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}

final class FooCallable
{
    public function echo(): string
    {
        return 'Symfony';
    }
}
