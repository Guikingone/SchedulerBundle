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
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\RunnerRegistry;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Transport\InMemoryTransport;
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
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\InMemoryStore;
use Throwable;
use function sprintf;

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
        self::assertCount(7, $consumeTasksCommand->getDefinition()->getOptions());

        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('limit'));
        self::assertTrue($consumeTasksCommand->getDefinition()->getOption('limit')->acceptValue());
        self::assertSame('Limit the number of tasks consumed', $consumeTasksCommand->getDefinition()->getOption('limit')->getDescription());
        self::assertSame('l', $consumeTasksCommand->getDefinition()->getOption('limit')->getShortcut());

        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('time-limit'));
        self::assertSame('Limit the time in seconds the worker can run', $consumeTasksCommand->getDefinition()->getOption('time-limit')->getDescription());
        self::assertSame('t', $consumeTasksCommand->getDefinition()->getOption('time-limit')->getShortcut());

        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('failure-limit'));
        self::assertTrue($consumeTasksCommand->getDefinition()->getOption('failure-limit')->acceptValue());
        self::assertSame('Limit the amount of task allowed to fail', $consumeTasksCommand->getDefinition()->getOption('failure-limit')->getDescription());
        self::assertSame('f', $consumeTasksCommand->getDefinition()->getOption('failure-limit')->getShortcut());

        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('wait'));
        self::assertFalse($consumeTasksCommand->getDefinition()->getOption('wait')->acceptValue());
        self::assertSame('Set the worker to wait for tasks every minutes', $consumeTasksCommand->getDefinition()->getOption('wait')->getDescription());
        self::assertSame('w', $consumeTasksCommand->getDefinition()->getOption('wait')->getShortcut());

        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('force'));
        self::assertFalse($consumeTasksCommand->getDefinition()->getOption('force')->acceptValue());
        self::assertSame('Force the worker to wait for tasks even if no tasks are currently available', $consumeTasksCommand->getDefinition()->getOption('force')->getDescription());
        self::assertNull($consumeTasksCommand->getDefinition()->getOption('force')->getShortcut());

        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('lazy'));
        self::assertFalse($consumeTasksCommand->getDefinition()->getOption('lazy')->acceptValue());
        self::assertSame('Force the scheduler to retrieve the tasks using lazy-loading', $consumeTasksCommand->getDefinition()->getOption('lazy')->getDescription());
        self::assertNull($consumeTasksCommand->getDefinition()->getOption('lazy')->getShortcut());

        self::assertTrue($consumeTasksCommand->getDefinition()->hasOption('strict'));
        self::assertFalse($consumeTasksCommand->getDefinition()->getOption('strict')->acceptValue());
        self::assertSame('Force the scheduler to check the date before retrieving the tasks', $consumeTasksCommand->getDefinition()->getOption('strict')->getDescription());
        self::assertNull($consumeTasksCommand->getDefinition()->getOption('strict')->getShortcut());

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

                Use the --force option to force the worker to wait for tasks every minutes even if no tasks are currently available:
                    <info>php %command.full_name% --force</info>

                Use the --lazy option to force the scheduler to retrieve the tasks using lazy-loading:
                    <info>php %command.full_name% --lazy</info>

                Use the --strict option to force the scheduler to check the date before retrieving the tasks:
                    <info>php %command.full_name% --strict</info>
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
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = new EventDispatcher();

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher);

        $scheduler->schedule(new NullTask('foo'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $commandTester->getDisplay());
        self::assertStringContainsString('The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testCommandCanConsumeTasksUsingLazyLoading(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = new EventDispatcher();

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher);

        $scheduler->schedule(new NullTask('foo'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([
            '--lazy' => true,
        ]);

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
        self::assertStringContainsString('- 10 tasks have been consumed', $commandTester->getDisplay());
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
    public function testCommandCanConsumeSchedulersWithFailureLimit(int $failureLimit, string $failureOutput): void
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
        self::assertStringContainsString($failureOutput, $commandTester->getDisplay());
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
        self::assertStringContainsString('- 10 tasks have been consumed or 10 tasks have failed', $commandTester->getDisplay());
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
        $eventDispatcher->expects(self::exactly(2))->method('addListener');
        $eventDispatcher->expects(self::never())->method('addSubscriber');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

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

        $task = new NullTask('foo', [
            'execution_memory_usage' => 9_507_552,
        ]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::exactly(2))->method('getDueTasks')->willReturn(new TaskList([$task]));

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->with(self::equalTo($task))->willReturn(new Output($task, 'Success output'));

        $worker = new Worker($scheduler, new RunnerRegistry([$runner]), $tracker, new WorkerMiddlewareStack(), $eventDispatcher, new LockFactory(new FlockStore()));

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher));
        $commandTester->execute([
            '--limit' => 1,
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once:', $commandTester->getDisplay());
        self::assertStringContainsString('- 1 task has been consumed', $commandTester->getDisplay());
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
    public function testCommandCanDisplayTaskOutputWithVeryVerboseOutputAndIncompleteExecutionState(): void
    {
        $eventDispatcher = new EventDispatcher();

        $task = new NullTask('random_incomplete', [
            'execution_memory_usage' => 9_507_552,
            'execution_state' => TaskInterface::INCOMPLETE,
        ]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::exactly(2))->method('getDueTasks')->willReturn(new TaskList([$task]));

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->with(self::equalTo($task))->willReturn(new Output($task, 'Success output'));

        $worker = new Worker($scheduler, new RunnerRegistry([$runner]), $tracker, new WorkerMiddlewareStack(), $eventDispatcher, new LockFactory(new InMemoryStore()));

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher));
        $commandTester->execute([
            '--limit' => 1,
        ], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once:', $commandTester->getDisplay());
        self::assertStringContainsString('- 1 task has been consumed', $commandTester->getDisplay());
        self::assertStringNotContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringContainsString('[WARNING] The task "random_incomplete" cannot be executed fully', $commandTester->getDisplay());
        self::assertStringContainsString('The task will be retried next time', $commandTester->getDisplay());
        self::assertStringContainsString('Success output', $commandTester->getDisplay());
        self::assertStringNotContainsString('Duration: < 1 sec', $commandTester->getDisplay());
        self::assertStringNotContainsString('Memory used: 9.1 MiB', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCanDisplayTaskOutputWithVeryVerboseOutputAndToRetryExecutionState(): void
    {
        $eventDispatcher = new EventDispatcher();

        $task = new NullTask('random_to_retry', [
            'execution_memory_usage' => 9_507_552,
            'execution_state' => TaskInterface::TO_RETRY,
        ]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::exactly(2))->method('getDueTasks')->willReturn(new TaskList([$task]));

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->with(self::equalTo($task))->willReturn(new Output($task, 'Success output'));

        $worker = new Worker($scheduler, new RunnerRegistry([$runner]), $tracker, new WorkerMiddlewareStack(), $eventDispatcher, new LockFactory(new InMemoryStore()));

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher));
        $commandTester->execute([
            '--limit' => 1,
        ], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once:', $commandTester->getDisplay());
        self::assertStringContainsString('- 1 task has been consumed', $commandTester->getDisplay());
        self::assertStringNotContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringContainsString('[WARNING] The task "random_to_retry" cannot be executed fully', $commandTester->getDisplay());
        self::assertStringContainsString('The task will be retried next time', $commandTester->getDisplay());
        self::assertStringContainsString('Success output', $commandTester->getDisplay());
        self::assertStringNotContainsString('Duration: < 1 sec', $commandTester->getDisplay());
        self::assertStringNotContainsString('Memory used: 9.1 MiB', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see TaskListInterface::add()}
     */
    public function testCommandCanDisplayTaskOutputWithVeryVerboseOutput(): void
    {
        $eventDispatcher = new EventDispatcher();

        $name = sprintf('bar_very_verbose_%s', uniqid('foo'));

        $task = new NullTask($name, [
            'execution_memory_usage' => 9_507_552,
        ]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::exactly(2))->method('getDueTasks')->willReturn(new TaskList([$task]));

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->with(self::equalTo($task))->willReturn(new Output($task, 'Success output'));

        $worker = new Worker($scheduler, new RunnerRegistry([$runner]), $tracker, new WorkerMiddlewareStack(), $eventDispatcher, new LockFactory(new FlockStore()));

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher));
        $commandTester->execute([
            '--limit' => 1,
        ], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once:', $commandTester->getDisplay());
        self::assertStringContainsString('- 1 task has been consumed', $commandTester->getDisplay());
        self::assertStringNotContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
        self::assertStringContainsString(sprintf('Output for task "%s":', $name), $commandTester->getDisplay());
        self::assertStringContainsString('Success output', $commandTester->getDisplay());
        self::assertStringContainsString(sprintf('Task "%s" succeed', $name), $commandTester->getDisplay());
        self::assertStringContainsString('Duration: < 1 sec', $commandTester->getDisplay());
        self::assertStringContainsString('Memory used: 9.1 MiB', $commandTester->getDisplay());
        self::assertStringNotContainsString(sprintf('Task failed: "%s"', $name), $commandTester->getDisplay());
    }

    public function testCommandCannotExecuteExternalProbeTask(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([
            new ProbeTask('foo', '_/probe'),
            new ProbeTask('bar', '/_second_probe'),
        ]));

        $worker = $this->createMock(WorkerInterface::class);

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, new EventDispatcher(), $logger));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('No due tasks found', $commandTester->getDisplay());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testCommandCanWaitForTasksWithoutPauseFilter(): void
    {
        $eventDispatcher = new EventDispatcher();

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher);

        $scheduler->schedule(new NullTask('foo'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, new EventDispatcher()));
        $commandTester->execute([
            '--wait' => true,
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[NOTE] The worker will wait for tasks every minutes', $commandTester->getDisplay());
        self::assertStringContainsString('// Quit the worker with CONTROL-C.', $commandTester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $commandTester->getDisplay());
    }

    /**
     * @group time-sensitive
     *
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testCommandCanAskForStrictDateCheckWithoutDueTasks(): void
    {
        $eventDispatcher = new EventDispatcher();

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher);

        $scheduler->schedule(new NullTask('foo'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('execute');

        $commandTester = new CommandTester(new ConsumeTasksCommand($scheduler, $worker, new EventDispatcher()));
        $commandTester->execute([
            '--strict' => true,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] No due tasks found', $commandTester->getDisplay());
    }

    /**
     * @return Generator<array<int, int|string>>
     */
    public function providerFailureLimitContext(): Generator
    {
        yield 'Multiple tasks' => [10, '10 tasks have failed'];
        yield 'Single task' => [1, '1 task has failed'];
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
