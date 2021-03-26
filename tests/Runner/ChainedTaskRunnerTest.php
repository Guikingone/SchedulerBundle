<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SchedulerBundle\Runner\ChainedTaskRunner;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedTaskRunnerTest extends TestCase
{
    public function testRunnerSupportTask(): void
    {
        $chainedTaskRunner = new ChainedTaskRunner([]);

        self::assertFalse($chainedTaskRunner->support(new ShellTask('foo', ['ls', '-al'])));
        self::assertTrue($chainedTaskRunner->support(new ChainedTask('foo')));
    }

    public function testRunnerCannotRunInvalidTask(): void
    {
        $chainedTaskRunner = new ChainedTaskRunner([]);

        $output = $chainedTaskRunner->run(new ShellTask('foo', ['ls', '-al']));

        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertNull($output->getOutput());
    }

    public function testRunnerCanRunTaskWithError(): void
    {
        $shellTask = new ShellTask('foo', ['ls', '-al']);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with(self::equalTo($shellTask))->willReturn(true);
        $runner->expects(self::once())->method('run')->with(self::equalTo($shellTask))->willThrowException(new RuntimeException('An error occurred'));

        $chainedTaskRunner = new ChainedTaskRunner([
            $runner,
        ]);

        $output = $chainedTaskRunner->run(new ChainedTask('bar', $shellTask));

        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertNotNull($output->getOutput());
        self::assertSame('An error occurred', $output->getOutput());
    }
}
