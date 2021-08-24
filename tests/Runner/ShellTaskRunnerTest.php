<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Runner\ShellTaskRunner;
use SchedulerBundle\Task\CallbackTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Tests\SchedulerBundle\Runner\Assets\FooTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTaskRunnerTest extends TestCase
{
    public function testRunnerCantSupportWrongTask(): void
    {
        $fooTask = new FooTask('test');

        $shellTaskRunner = new ShellTaskRunner();

        self::assertFalse($shellTaskRunner->support($fooTask));
        self::assertTrue($shellTaskRunner->support(new ShellTask('test', ['echo', 'Symfony'])));
    }

    public function testRunnerCannotRunInvalidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $callbackTask = new CallbackTask('foo', ['echo', 'Symfony']);

        $shellTaskRunner = new ShellTaskRunner();
        $output = $shellTaskRunner->run($callbackTask, $worker);

        self::assertNull($callbackTask->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertSame($callbackTask, $output->getTask());
    }

    public function testRunnerCanSupportValidTaskWithoutOutput(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $shellTask = new ShellTask('test', ['echo', 'Symfony']);
        $shellTask->setEnvironmentVariables(['env' => 'test']);
        $shellTask->setTimeout(10);

        $shellTaskRunner = new ShellTaskRunner();
        self::assertTrue($shellTaskRunner->support($shellTask));
        self::assertNull($shellTaskRunner->run($shellTask, $worker)->getOutput());
        self::assertNull($shellTaskRunner->run($shellTask, $worker)->getTask()->getExecutionState());
    }

    public function testRunnerCanSupportValidTaskWithOutput(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $shellTask = new ShellTask('test', ['echo', 'Symfony']);
        $shellTask->setEnvironmentVariables(['env' => 'test']);
        $shellTask->setOutput(true);

        $shellTaskRunner = new ShellTaskRunner();
        self::assertTrue($shellTaskRunner->support($shellTask));
        self::assertSame('Symfony', $shellTaskRunner->run($shellTask, $worker)->getOutput());
        self::assertNull($shellTaskRunner->run($shellTask, $worker)->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnEmptyOutputOnBackgroundTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $shellTask = new ShellTask('test', ['echo', 'Symfony']);
        $shellTask->setEnvironmentVariables(['env' => 'test']);
        $shellTask->setOutput(true);
        $shellTask->setBackground(true);

        $shellTaskRunner = new ShellTaskRunner();
        self::assertTrue($shellTaskRunner->support($shellTask));
        self::assertSame('Task is running in background, output is not available', $shellTaskRunner->run($shellTask, $worker)->getOutput());
        self::assertSame(TaskInterface::INCOMPLETE, $shellTaskRunner->run($shellTask, $worker)->getTask()->getExecutionState());
    }
}
