<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use SchedulerBundle\Command\RemoveFailedTaskCommand;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RemoveFailedTaskCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);

        self::assertSame('scheduler:remove:failed', $command->getName());
        self::assertSame('Remove given task from the scheduler', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasArgument('name'));
        self::assertSame('The name of the task to remove', $command->getDefinition()->getArgument('name')->getDescription());
        self::assertTrue($command->getDefinition()->getArgument('name')->isRequired());
        self::assertTrue($command->getDefinition()->hasOption('force'));
        self::assertSame('Force the operation without confirmation', $command->getDefinition()->getOption('force')->getDescription());
        self::assertSame('f', $command->getDefinition()->getOption('force')->getShortcut());
        self::assertSame(
            $command->getHelp(),
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

    public function testCommandCannotRemoveUndefinedTask(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn(null);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('[ERROR] The task "foo" does not fails', $tester->getDisplay());
    }

    public function testCommandCannotRemoveTaskWithException(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule')->willThrowException(new Exception('Random error'));

        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('[ERROR] An error occurred when trying to unschedule the task:', $tester->getDisplay());
        self::assertStringContainsString('Random error', $tester->getDisplay());
    }

    public function testCommandCannotRemoveWithoutConfirmationOrForceOption(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('unschedule');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('[NOTE] The task "foo" has not been unscheduled', $tester->getDisplay());
    }

    public function testCommandCanRemoveTaskWithForceOption(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'foo',
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been unscheduled', $tester->getDisplay());
    }

    public function testCommandCanRemoveTask(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] The task "foo" has been unscheduled', $tester->getDisplay());
    }
}
