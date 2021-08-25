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

        self::assertFalse($callbackTaskRunner->support(new ShellTask('foo', ['echo', 'Symfony!'])));
        self::assertTrue($callbackTaskRunner->support(new CallbackTask('foo', fn (): int => 1 + 1)));
    }

    public function testRunnerCannotExecuteInvalidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('execute');

        $callbackTaskRunner = new CallbackTaskRunner();
        $shellTask = new ShellTask('foo', ['echo', 'Symfony!']);

        $output = $callbackTaskRunner->run($shellTask, $worker);
        self::assertSame(Output::ERROR, $output->getType());
        self::assertNull($shellTask->getExecutionState());
        self::assertNull($output->getOutput());
        self::assertSame($shellTask, $output->getTask());
    }

    public function testRunnerCanExecuteValidTaskWithExtraSpacedOutput(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (): string => '   hello');

        $output = $callbackTaskRunner->run($callbackTask, $worker);

        self::assertNull($callbackTask->getExecutionState());
        self::assertSame('hello', $output->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (): int => 1 + 1);

        $output = $callbackTaskRunner->run($callbackTask, $worker);

        self::assertNull($callbackTask->getExecutionState());
        self::assertSame('2', $output->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithCallable(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (): string => (new FooCallable())->echo());

        $output = $callbackTaskRunner->run($callbackTask, $worker);

        self::assertNull($callbackTask->getExecutionState());
        self::assertSame('Symfony', $callbackTaskRunner->run($callbackTask, $worker)->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithArguments(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (int $a, int $b): int => $a * $b, [1, 2]);

        $output = $callbackTaskRunner->run($callbackTask, $worker);

        self::assertNull($callbackTask->getExecutionState());
        self::assertSame('2', $callbackTaskRunner->run($callbackTask, $worker)->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteInvalidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (int $a, $b): int => $a * $b, [1]);

        $output = $callbackTaskRunner->run($callbackTask, $worker);

        self::assertNull($callbackTask->getExecutionState());
        self::assertNull($output->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteTaskWithFalseReturn(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (): bool => false);

        $output = $callbackTaskRunner->run($callbackTask, $worker);

        self::assertNull($callbackTask->getExecutionState());
        self::assertNull($output->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteTaskWithTrueReturn(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $callbackTaskRunner = new CallbackTaskRunner();
        $callbackTask = new CallbackTask('foo', fn (): bool => true);

        $output = $callbackTaskRunner->run($callbackTask, $worker);

        self::assertNull($callbackTask->getExecutionState());
        self::assertSame('1', $output->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }
}
