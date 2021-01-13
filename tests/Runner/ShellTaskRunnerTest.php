<?php

declare(strict_types=1);

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

        self::assertFalse($runner->support($task));
        self::assertTrue($runner->support(new ShellTask('test', ['echo', 'Symfony'])));
    }

    public function testRunnerCanSupportValidTaskWithoutOutput(): void
    {
        $task = new ShellTask('test', ['echo', 'Symfony']);
        $task->setEnvironmentVariables(['env' => 'test']);
        $task->setTimeout(10);

        $runner = new ShellTaskRunner();
        self::assertTrue($runner->support($task));
        self::assertNull($runner->run($task)->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $runner->run($task)->getTask()->getExecutionState());
    }

    public function testRunnerCanSupportValidTaskWithOutput(): void
    {
        $task = new ShellTask('test', ['echo', 'Symfony']);
        $task->setEnvironmentVariables(['env' => 'test']);
        $task->setOutput(true);

        $runner = new ShellTaskRunner();
        self::assertTrue($runner->support($task));
        self::assertSame('Symfony', $runner->run($task)->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $runner->run($task)->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnEmptyOutputOnBackgroundTask(): void
    {
        $task = new ShellTask('test', ['echo', 'Symfony']);
        $task->setEnvironmentVariables(['env' => 'test']);
        $task->setOutput(true);
        $task->setBackground(true);

        $runner = new ShellTaskRunner();
        self::assertTrue($runner->support($task));
        self::assertSame('Task is running in background, output is not available', $runner->run($task)->getOutput());
        self::assertSame(TaskInterface::INCOMPLETE, $runner->run($task)->getTask()->getExecutionState());
    }
}

final class FooTask extends AbstractTask
{
}
