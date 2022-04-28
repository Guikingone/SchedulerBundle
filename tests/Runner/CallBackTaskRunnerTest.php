<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Runner\CallbackTaskRunner;
use SchedulerBundle\Task\CallbackTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Worker\WorkerInterface;
use Tests\SchedulerBundle\Runner\Assets\FooCallable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallBackTaskRunnerTest extends TestCase
{
    public function testRunnerCannotSupportInvalidTask(): void
    {
        $callbackTaskRunner = new CallbackTaskRunner();

        self::assertFalse($callbackTaskRunner->support(task: new ShellTask(name: 'foo', command: ['echo', 'Symfony!'])));
        self::assertTrue($callbackTaskRunner->support(task: new CallbackTask(name: 'foo', callback: static fn (): int => 1 + 1)));
    }

    public function testRunnerCannotExecuteInvalidTask(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);
        $worker->expects(self::never())->method(constraint: 'execute');

        $callbackTaskRunner = new CallbackTaskRunner();
        $shellTask = new ShellTask(name: 'foo', command: ['echo', 'Symfony!']);

        $output = $callbackTaskRunner->run(task: $shellTask, worker: $worker);
        self::assertSame(expected: Output::ERROR, actual: $output->getType());
        self::assertNull(actual: $shellTask->getExecutionState());
        self::assertNull(actual: $output->getOutput());
        self::assertSame(expected: $shellTask, actual: $output->getTask());
    }

    public function testRunnerCanExecuteValidTaskWithExtraSpacedOutput(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask(name: 'foo', callback: static fn (): string => '   hello');

        $output = $callbackTaskRunner->run(task: $callbackTask, worker: $worker);

        self::assertSame(expected: Output::SUCCESS, actual: $output->getType());
        self::assertNull(actual: $callbackTask->getExecutionState());
        self::assertSame(expected: 'hello', actual: $output->getOutput());
        self::assertNull(actual: $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTask(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask(name: 'foo', callback: static fn (): int => 1 + 1);

        $output = $callbackTaskRunner->run(task: $callbackTask, worker: $worker);

        self::assertSame(expected: Output::SUCCESS, actual: $output->getType());
        self::assertNull(actual: $callbackTask->getExecutionState());
        self::assertSame(expected: '2', actual: $output->getOutput());
        self::assertNull(actual: $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithCallable(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask(name: 'foo', callback: static fn (): string => (new FooCallable())->echo());

        $output = $callbackTaskRunner->run(task: $callbackTask, worker: $worker);

        self::assertSame(expected: Output::SUCCESS, actual: $output->getType());
        self::assertNull(actual: $callbackTask->getExecutionState());
        self::assertSame(expected: 'Symfony', actual: $callbackTaskRunner->run(task: $callbackTask, worker: $worker)->getOutput());
        self::assertNull(actual: $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithArguments(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask(name: 'foo', callback: static fn (int $a, int $b): int => $a * $b, arguments: [1, 2]);

        $output = $callbackTaskRunner->run(task: $callbackTask, worker: $worker);

        self::assertSame(Output::SUCCESS, $output->getType());
        self::assertNull($callbackTask->getExecutionState());
        self::assertSame('2', $callbackTaskRunner->run($callbackTask, $worker)->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteInvalidTask(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask(name: 'foo', callback: static fn (int $a, $b): int => $a * $b, arguments: [1]);

        $output = $callbackTaskRunner->run(task: $callbackTask, worker: $worker);

        self::assertSame(expected: Output::ERROR, actual: $output->getType());
        self::assertNull(actual: $callbackTask->getExecutionState());
        self::assertNull(actual: $output->getOutput());
        self::assertNull(actual: $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteTaskWithFalseReturn(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask(name: 'foo', callback: static fn (): bool => false);

        $output = $callbackTaskRunner->run(task: $callbackTask, worker: $worker);

        self::assertSame(expected: Output::ERROR, actual: $output->getType());
        self::assertNull(actual: $callbackTask->getExecutionState());
        self::assertNull(actual: $output->getOutput());
        self::assertNull(actual: $output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteTaskWithTrueReturn(): void
    {
        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask(name: 'foo', callback: static fn (): bool => true);

        $output = $callbackTaskRunner->run(task: $callbackTask, worker: $worker);

        self::assertSame(expected: Output::SUCCESS, actual: $output->getType());
        self::assertNull(actual: $callbackTask->getExecutionState());
        self::assertSame(expected: '1', actual: $output->getOutput());
        self::assertNull(actual: $output->getTask()->getExecutionState());
    }
}
