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
        $callbackTaskRunner = new CallbackTaskRunner();

        self::assertFalse($callbackTaskRunner->support(new ShellTask('foo', ['echo', 'Symfony!'])));

        $task = new CallbackTask('foo', fn (): int => 1 + 1);

        self::assertTrue($callbackTaskRunner->support($task));
    }

    public function testRunnerCannotExecuteInvalidTask(): void
    {
        $callbackTaskRunner = new CallbackTaskRunner();
        $shellTask = new ShellTask('foo', ['echo', 'Symfony!']);

        $output = $callbackTaskRunner->run($shellTask);
        self::assertSame(Output::ERROR, $output->getType());
        self::assertSame(TaskInterface::ERRORED, $shellTask->getExecutionState());
        self::assertNull($output->getOutput());
        self::assertSame($shellTask, $output->getTask());
    }

    public function testRunnerCanExecuteValidTaskWithExtraSpacedOutput(): void
    {
        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (): string => '   hello');

        $output = $callbackTaskRunner->run($callbackTask);

        self::assertSame(TaskInterface::SUCCEED, $callbackTask->getExecutionState());
        self::assertSame('hello', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTask(): void
    {
        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (): int => 1 + 1);

        $output = $callbackTaskRunner->run($callbackTask);

        self::assertSame(TaskInterface::SUCCEED, $callbackTask->getExecutionState());
        self::assertSame('2', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithCallable(): void
    {
        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', [new FooCallable(), 'echo']);

        $output = $callbackTaskRunner->run($callbackTask);

        self::assertSame(TaskInterface::SUCCEED, $callbackTask->getExecutionState());
        self::assertSame('Symfony', $callbackTaskRunner->run($callbackTask)->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithArguments(): void
    {
        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn ($a, $b): int => $a * $b, [1, 2]);

        $output = $callbackTaskRunner->run($callbackTask);

        self::assertSame(TaskInterface::SUCCEED, $callbackTask->getExecutionState());
        self::assertSame('2', $callbackTaskRunner->run($callbackTask)->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteInvalidTask(): void
    {
        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn ($a, $b): int => $a * $b, [1]);

        $output = $callbackTaskRunner->run($callbackTask);

        self::assertSame(TaskInterface::ERRORED, $callbackTask->getExecutionState());
        self::assertNull($output->getOutput());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteTaskWithFalseReturn(): void
    {
        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (): bool => false);

        $output = $callbackTaskRunner->run($callbackTask);

        self::assertSame(TaskInterface::ERRORED, $callbackTask->getExecutionState());
        self::assertNull($output->getOutput());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteTaskWithTrueReturn(): void
    {
        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (): bool => true);

        $output = $callbackTaskRunner->run($callbackTask);

        self::assertSame(TaskInterface::SUCCEED, $callbackTask->getExecutionState());
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
