<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use Exception;
use ArrayIterator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\EventListener\StopWorkerOnFailureLimitSubscriber;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\EventListener\StopWorkerOnTimeLimitSubscriber;
use Symfony\Component\Console\Application;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConsumeTasksCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $command = new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger);

        self::assertSame('scheduler:consume', $command->getName());
        self::assertSame('Consumes due tasks', $command->getDescription());
        self::assertSame(0, $command->getDefinition()->getArgumentCount());
        self::assertCount(4, $command->getDefinition()->getOptions());
        self::assertTrue($command->getDefinition()->hasOption('limit'));
        self::assertSame('Limit the number of tasks consumed', $command->getDefinition()->getOption('limit')->getDescription());
        self::assertSame('l', $command->getDefinition()->getOption('limit')->getShortcut());
        self::assertTrue($command->getDefinition()->hasOption('time-limit'));
        self::assertSame('Limit the time in seconds the worker can run', $command->getDefinition()->getOption('time-limit')->getDescription());
        self::assertSame('t', $command->getDefinition()->getOption('time-limit')->getShortcut());
        self::assertTrue($command->getDefinition()->hasOption('failure-limit'));
        self::assertSame('Limit the amount of task allowed to fail', $command->getDefinition()->getOption('failure-limit')->getDescription());
        self::assertSame('f', $command->getDefinition()->getOption('failure-limit')->getShortcut());
        self::assertTrue($command->getDefinition()->hasOption('wait'));
        self::assertFalse($command->getDefinition()->getOption('wait')->acceptValue());
        self::assertSame('Set the worker to wait for tasks every minutes', $command->getDefinition()->getOption('wait')->getDescription());
        self::assertSame('w', $command->getDefinition()->getOption('wait')->getShortcut());
        self::assertSame(
            $command->getHelp(),
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
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);

        $command = new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No due tasks found', $tester->getDisplay());
    }

    public function testCommandCanConsumeTasks(): void
    {
        $eventDispatcher = new EventDispatcher();
        $logger = $this->createMock(LoggerInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::exactly(2))->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $command = new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $tester->getDisplay());
        self::assertStringContainsString('The task output can be displayed if the -vv option is used', $tester->getDisplay());
    }

    public function testCommandCannotConsumeTasksWithError(): void
    {
        $eventDispatcher = new EventDispatcher();
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::exactly(2))->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->willThrowException(new Exception('Random error occurred'));

        $command = new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('An error occurred when executing the tasks', $tester->getDisplay());
        self::assertStringContainsString('The task output can be displayed if the -vv option is used', $tester->getDisplay());
        self::assertStringContainsString('Random error occurred', $tester->getDisplay());
    }

    public function testCommandCanConsumeSchedulersWithTaskLimit(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTaskLimitSubscriber(10, $logger));

        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::exactly(2))->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $command = new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            '--limit' => 10,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once 10 tasks has been consumed', $tester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $tester->getDisplay());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $tester->getDisplay());
    }

    public function testCommandCanConsumeSchedulersWithTimeLimit(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTimeLimitSubscriber(10, $logger));

        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::exactly(2))->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $command = new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            '--time-limit' => 10,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once it has been running for 10 seconds', $tester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $tester->getDisplay());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $tester->getDisplay());
    }

    public function testCommandCanConsumeSchedulersWithFailureLimit(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnFailureLimitSubscriber(10, $logger));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::exactly(2))->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $command = new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            '--failure-limit' => 10,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once 10 tasks have failed', $tester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $tester->getDisplay());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $tester->getDisplay());
    }

    public function testCommandCanConsumeSchedulersWithWait(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::never())->method('addSubscriber');

        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::exactly(2))->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with([
            'sleepUntilNextMinute' => true,
        ]);

        $command = new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher, $logger);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            '--wait' => true,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The worker will wait for tasks every minutes', $tester->getDisplay());
        self::assertStringContainsString('[NOTE] The task output can be displayed if the -vv option is used', $tester->getDisplay());
        self::assertStringContainsString('Quit the worker with CONTROL-C.', $tester->getDisplay());
    }

    public function testCommandCanDisplayTaskOutput(): void
    {
        $eventDispatcher = new EventDispatcher();

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExecutionComputationTime')->willReturn(10.05);
        $task->expects(self::once())->method('getExecutionMemoryUsage')->willReturn(9_507_552);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::exactly(2))->method('count')->willReturn(1);
        $taskList->expects(self::once())->method('getIterator')->willReturn(new ArrayIterator([$task]));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::exactly(2))->method('getDueTasks')->willReturn($taskList);

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->with(self::equalTo($task))->willReturn(new Output($task, 'Success output'));

        $worker = new Worker($scheduler, [$runner], $tracker, $eventDispatcher);

        $command = new ConsumeTasksCommand($scheduler, $worker, $eventDispatcher);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:consume'));
        $tester->execute([
            '--limit' => 1,
        ], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The worker will automatically exit once 1 tasks has been consumed.', $tester->getDisplay());
        self::assertStringContainsString('Output for task "foo":', $tester->getDisplay());
        self::assertStringContainsString('Success output', $tester->getDisplay());
        self::assertStringContainsString('Task "foo" succeed', $tester->getDisplay());
        self::assertStringContainsString('Duration: < 1 sec', $tester->getDisplay());
        self::assertStringContainsString('Memory used: 9.1 MiB', $tester->getDisplay());
        self::assertStringNotContainsString('Task failed: "foo"', $tester->getDisplay());
    }
}
