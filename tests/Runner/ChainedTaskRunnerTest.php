<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SchedulerBundle\Runner\ChainedTaskRunner;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
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

        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertNull($output->getOutput());
    }

    public function testRunnerCanRunTaskWithError(): void
    {
        $shellTask = new ShellTask('foo', ['ls', '-al']);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getOptions')->willReturn([
            'executedTasksCount' => 10,
            'forkedFrom' => $worker,
            'isFork' => true,
            'isRunning' => false,
            'lastExecutedTask' => null,
            'sleepDurationDelay' => 1,
            'sleepUntilNextMinute' => true,
            'shouldStop' => false,
            'shouldRetrieveTasksLazily' => false,
        ]);
        $worker->expects(self::once())->method('fork')->willReturnSelf();
        $worker->expects(self::once())->method('execute')
            ->with(self::equalTo([
                'executedTasksCount' => 0,
                'forkedFrom' => $worker,
                'isFork' => true,
                'isRunning' => false,
                'lastExecutedTask' => null,
                'sleepDurationDelay' => 1,
                'sleepUntilNextMinute' => false,
                'shouldStop' => false,
                'shouldRetrieveTasksLazily' => false,
            ]), self::equalTo($shellTask))
            ->willThrowException(new RuntimeException('An error occurred'))
        ;

        $chainedTaskRunner = new ChainedTaskRunner();

        $output = $chainedTaskRunner->run(new ChainedTask('bar', $shellTask), $worker);

        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertNotNull($output->getOutput());
        self::assertSame('An error occurred', $output->getOutput());
    }
}
