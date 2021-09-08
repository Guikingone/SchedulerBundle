<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SchedulerBundle\Runner\ChainedTaskRunner;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedTaskRunnerTest extends TestCase
{
    public function testRunnerSupportTask(): void
    {
        $chainedTaskRunner = new ChainedTaskRunner();

        self::assertFalse($chainedTaskRunner->support(new ShellTask('foo', ['ls', '-al'])));
        self::assertTrue($chainedTaskRunner->support(new ChainedTask('foo')));
    }

    public function testRunnerCannotRunInvalidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $chainedTaskRunner = new ChainedTaskRunner();

        $output = $chainedTaskRunner->run(new ShellTask('foo', ['ls', '-al']), $worker);

        self::assertNull($output->getTask()->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertNull($output->getOutput());
    }

    public function testRunnerCanRunTaskWithError(): void
    {
        $shellTask = new ShellTask('foo', ['ls', '-al']);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('getConfiguration');
        $worker->expects(self::once())->method('fork')->willReturnSelf();
        $worker->expects(self::once())->method('execute')
            ->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($shellTask))
            ->willThrowException(new RuntimeException('An error occurred'))
        ;
        $worker->expects(self::once())->method('stop');

        $chainedTaskRunner = new ChainedTaskRunner();

        $output = $chainedTaskRunner->run(new ChainedTask('bar', $shellTask), $worker);

        self::assertNull($output->getTask()->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertNotNull($output->getOutput());
        self::assertSame('An error occurred', $output->getOutput());
    }
}
