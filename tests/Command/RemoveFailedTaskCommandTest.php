<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use Exception;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Command\RemoveFailedTaskCommand;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Scheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RemoveFailedTaskCommandTest extends TestCase
{
    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testCommandIsConfigured(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack([]), eventDispatcher: new EventDispatcher());

        $worker = $this->createMock(originalClassName: WorkerInterface::class);

        $removeFailedTaskCommand = new RemoveFailedTaskCommand(scheduler: $scheduler, worker: $worker);

        self::assertSame('scheduler:remove:failed', $removeFailedTaskCommand->getName());
        self::assertSame('Remove given task from the scheduler', $removeFailedTaskCommand->getDescription());
        self::assertTrue($removeFailedTaskCommand->getDefinition()->hasArgument('name'));
        self::assertSame('The name of the task to remove', $removeFailedTaskCommand->getDefinition()->getArgument('name')->getDescription());
        self::assertTrue($removeFailedTaskCommand->getDefinition()->getArgument('name')->isRequired());
        self::assertTrue($removeFailedTaskCommand->getDefinition()->hasOption('force'));
        self::assertSame('Force the operation without confirmation', $removeFailedTaskCommand->getDefinition()->getOption('force')->getDescription());
        self::assertSame('f', $removeFailedTaskCommand->getDefinition()->getOption('force')->getShortcut());
        self::assertSame(
            $removeFailedTaskCommand->getHelp(),
            <<<'EOF'
                The <info>%command.name%</info> command remove a failed task.

                    <info>php %command.full_name%</info>

                Use the task-name argument to specify the task to remove:
                    <info>php %command.full_name% <task-name></info>

                Use the --force option to force the task deletion without asking for confirmation:
                    <info>php %command.full_name% <task-name> --force</info>
                EOF
        );
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testCommandCanSuggestFailedTasks(): void
    {
        $scheduler = new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack([]), eventDispatcher: new EventDispatcher());

        $worker = $this->createMock(originalClassName: WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn(new TaskList(tasks: [
            new NullTask('foo'),
            new NullTask('bar'),
        ]));

        $removeFailedTaskCommand = new RemoveFailedTaskCommand($scheduler, $worker);

        $tester = new CommandCompletionTester($removeFailedTaskCommand);
        $suggestions = $tester->complete(['f', 'b']);

        self::assertSame(['foo', 'bar'], $suggestions);
    }

    public function testCommandCannotRemoveUndefinedTask(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn(new TaskList());

        $removeFailedTaskCommand = new RemoveFailedTaskCommand($scheduler, $worker);
        $commandTester = new CommandTester($removeFailedTaskCommand);
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('[ERROR] The task "foo" does not fails', $commandTester->getDisplay());
    }

    public function testCommandCannotRemoveTaskWithException(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule')->willThrowException(new Exception('Random error'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $removeFailedTaskCommand = new RemoveFailedTaskCommand($scheduler, $worker);
        $commandTester = new CommandTester($removeFailedTaskCommand);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('[ERROR] An error occurred when trying to unschedule the task:', $commandTester->getDisplay());
        self::assertStringContainsString('Random error', $commandTester->getDisplay());
    }

    public function testCommandCannotRemoveWithoutConfirmationOrForceOption(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('unschedule');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $removeFailedTaskCommand = new RemoveFailedTaskCommand($scheduler, $worker);
        $commandTester = new CommandTester($removeFailedTaskCommand);
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('[NOTE] The task "foo" has not been unscheduled', $commandTester->getDisplay());
    }

    public function testCommandCanRemoveTaskWithForceOption(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $removeFailedTaskCommand = new RemoveFailedTaskCommand($scheduler, $worker);
        $commandTester = new CommandTester($removeFailedTaskCommand);
        $commandTester->execute([
            'name' => 'foo',
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been unscheduled', $commandTester->getDisplay());
    }

    public function testCommandCanRemoveTask(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $removeFailedTaskCommand = new RemoveFailedTaskCommand($scheduler, $worker);
        $commandTester = new CommandTester($removeFailedTaskCommand);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been unscheduled', $commandTester->getDisplay());
    }
}
