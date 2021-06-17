<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use DateTimeImmutable;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\EventListener\StopWorkerOnFailureLimitSubscriber;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\EventListener\StopWorkerOnTimeLimitSubscriber;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\RunnerRegistry;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskList;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use SchedulerBundle\Command\ConsumeTasksCommand;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\Worker;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConsumeTasksCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $logger = $this->createMock(LoggerInterface::class);

        $consumeTasksCommand = new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger);

        self::assertSame('scheduler:consume', $consumeTasksCommand->getName());
        self::assertSame('Consumes due tasks', $consumeTasksCommand->getDescription());
        self::assertSame(0, $consumeTasksCommand->getDefinition()->getArgumentCount());
        self::assertCount(4, $consumeTasksCommand->getDefinition()->getOptions());
        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('limit'));
        self::assertSame('Limit the number of tasks consumed', $consumeTasksCommand->getDefinition()->getOption('limit')->getDescription());
        self::assertSame('l', $consumeTasksCommand->getDefinition()->getOption('limit')->getShortcut());
        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('time-limit'));
        self::assertSame('Limit the time in seconds the worker can run', $consumeTasksCommand->getDefinition()->getOption('time-limit')->getDescription());
        self::assertSame('t', $consumeTasksCommand->getDefinition()->getOption('time-limit')->getShortcut());
        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('failure-limit'));
        self::assertSame('Limit the amount of task allowed to fail', $consumeTasksCommand->getDefinition()->getOption('failure-limit')->getDescription());
        self::assertSame('f', $consumeTasksCommand->getDefinition()->getOption('failure-limit')->getShortcut());
        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('wait'));
        self::assertFalse($consumeTasksCommand->getDefinition()->getOption('wait')->acceptValue());
        self::assertSame('Set the worker to wait for tasks every minutes', $consumeTasksCommand->getDefinition()->getOption('wait')->getDescription());
        self::assertSame('w', $consumeTasksCommand->getDefinition()->getOption('wait')->getShortcut());
        self::assertSame(
            $consumeTasksCommand->getHelp(),
            <<<'EOF'
                The <info>%command.name%</info> command consumes due tasks.

                    <info>php %command.full_name%</info>

                Use the --limit option to limit the number of tasks consumed:
                    <info>php %command.full_name% --limit=10</info>

                Use the --time-limit option to stop the worker when the given time limit (in seconds) is reached:
                    <info>php %command.full_name% --time-limit=3600</info>

                Use the --failure-limit option to stop the worker when the given amount of failed tasks is reached:
                    <info>php %command.full_name% --failure-limit=5</info>

                Use the --wait option to set the worker to wait for tasks every minutes:
                    <info>php %command.full_name% --wait</info>
                EOF
        );
    }

    public function testCommandCannotConsumeEmptyTasks(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList());

        $worker = $this->createMock(WorkerInterface::class);

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('No due tasks found', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCanConsumeAlreadyExecutedTasks(): void
    {
        $eventDispatcher = new EventDispatcher();
        $logger = $this->createMock(LoggerInterface::class);

        $task = new NullTask('foo', [
            'last_execution' => new DateTimeImmutable(),
            'state' => TaskInterface::PAUSED,
        ]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('execute');

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] Each tasks has already been executed for the current minute', $commandTester->getDisplay());
        self::assertStringContainsString(sprintf('Consider calling this command again at "%s"', (new DateTimeImmutable('+ 1 minute'))->format('Y-m-d h:i')), $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCanConsumeTasks(): void
    {
        $eventDispatcher = new EventDispatcher();
        $logger = $this->createMock(LoggerInterface::class);

        $task = new NullTask('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $commandTester->getDisplay());
        self::assertStringContainsString('The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCannotConsumeTasksWithError(): void
    {
        $eventDispatcher = new EventDispatcher();
        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->willThrowException(new Exception('Random error occurred'));

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('An error occurred when executing the tasks', $commandTester->getDisplay());
        self::assertStringContainsString('The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringContainsString('Random error occurred', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCanConsumeSchedulersWithTaskLimit(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTaskLimitSubscriber(10, $logger));

        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([
            '--limit' => 10,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once:', $commandTester->getDisplay());
        self::assertStringContainsString('- 10 tasks has been consumed', $commandTester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $commandTester->getDisplay());
    }

    /**
     * @dataProvider provideLimitOption
     *
     * @param int|string $limit
     *
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCanConsumeSchedulersWithTimeLimit($limit): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTimeLimitSubscriber(10, $logger));

        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([
            '--time-limit' => $limit,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once:', $commandTester->getDisplay());
        self::assertStringContainsString('- it has been running for 10 seconds', $commandTester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $commandTester->getDisplay());
    }

    /**
     * @dataProvider providerFailureLimitContext
     *
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCanConsumeSchedulersWithFailureLimit(int $failureLimit): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnFailureLimitSubscriber($failureLimit, $logger));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([
            '--failure-limit' => $failureLimit,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once:', $commandTester->getDisplay());
        self::assertStringContainsString('have failed', $commandTester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCanConsumeSchedulersWithFailureLimitAndTaskLimit(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::exactly(2))->method('addSubscriber')
            ->withConsecutive(
                [new StopWorkerOnTaskLimitSubscriber(10, $logger)],
                [new StopWorkerOnFailureLimitSubscriber(10, $logger)]
            )
        ;

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([
            '--failure-limit' => 10,
            '--limit' => 10,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once:', $commandTester->getDisplay());
        self::assertStringContainsString('- 10 tasks has been consumed or 10 tasks have failed', $commandTester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCanConsumeSchedulersWithWait(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::never())->method('addSubscriber');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with([
            'sleepUntilNextMinute' => true,
        ]);

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([
            '--wait' => true,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will wait for tasks every minutes', $commandTester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCannotDisplayTaskOutputWithoutVeryVerbose(): void
    {
        $eventDispatcher = new EventDispatcher();

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(5))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(5))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getExecutionComputationTime')->willReturn(10.05);
        $task->expects(self::once())->method('getExecutionMemoryUsage')->willReturn(9_507_552);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::exactly(2))->method('getDueTasks')->willReturn(new TaskList([$task]));

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->with(self::equalTo($task))->willReturn(new Output($task, 'Success output'));

        $worker = new Worker($scheduler, new RunnerRegistry([$runner]), $tracker, new WorkerMiddlewareStack(), $eventDispatcher);

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher));
        $commandTester->execute([
            '--limit' => 1,
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once:', $commandTester->getDisplay());
        self::assertStringContainsString('- 1 tasks has been consumed', $commandTester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringNotContainsString('Output for task "foo":', $commandTester->getDisplay());
        self::assertStringNotContainsString('Success output', $commandTester->getDisplay());
        self::assertStringContainsString('Task "foo" succeed', $commandTester->getDisplay());
        self::assertStringContainsString('Duration: < 1 sec', $commandTester->getDisplay());
        self::assertStringContainsString('Memory used: 9.1 MiB', $commandTester->getDisplay());
        self::assertStringNotContainsString('Task failed: "foo"', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCanDisplayTaskOutputWithVeryVerboseOutput(): void
    {
        $eventDispatcher = new EventDispatcher();

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(5))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getExecutionComputationTime')->willReturn(10.05);
        $task->expects(self::once())->method('getExecutionMemoryUsage')->willReturn(9_507_552);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::exactly(2))->method('getDueTasks')->willReturn(new TaskList([$task]));

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->with(self::equalTo($task))->willReturn(new Output($task, 'Success output'));

        $worker = new Worker($scheduler, new RunnerRegistry([$runner]), $tracker, new WorkerMiddlewareStack(), $eventDispatcher);

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher));
        $commandTester->execute([
            '--limit' => 1,
        ], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once:', $commandTester->getDisplay());
        self::assertStringContainsString('- 1 tasks has been consumed', $commandTester->getDisplay());
        self::assertStringNotContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringContainsString('Output for task "foo":', $commandTester->getDisplay());
        self::assertStringContainsString('Success output', $commandTester->getDisplay());
        self::assertStringContainsString('Task "foo" succeed', $commandTester->getDisplay());
        self::assertStringContainsString('Duration: < 1 sec', $commandTester->getDisplay());
        self::assertStringContainsString('Memory used: 9.1 MiB', $commandTester->getDisplay());
        self::assertStringNotContainsString('Task failed: "foo"', $commandTester->getDisplay());
    }

    public function testCommandCannotExecuteExternalProbeTask(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new ProbeTask('foo', '_/probe'),
            new ProbeTask('bar', '/_second_probe'),
        ]));

        $worker = $this->createMock(WorkerInterface::class);

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('No due tasks found', $commandTester->getDisplay());
    }

    /**
     * @return Generator<array<int, int>>
     */
    public function providerFailureLimitContext(): Generator
    {
        yield 'Multiple tasks' => [10];
        yield 'Single task' => [1];
    }

    /**
     * @return Generator<array<int, int|string>>
     */
    public function provideLimitOption(): Generator
    {
        yield [10];
        yield ['10'];
    }
}
