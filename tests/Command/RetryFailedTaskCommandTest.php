<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\Task\TaskList;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use SchedulerBundle\Command\RetryFailedTaskCommand;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RetryFailedTaskCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $retryFailedTaskCommand = new RetryFailedTaskCommand($worker, $eventDispatcher);

        self::assertSame('scheduler:retry:failed', $retryFailedTaskCommand->getName());
        self::assertSame('Retries one or more tasks from the failed tasks', $retryFailedTaskCommand->getDescription());
        self::assertTrue($retryFailedTaskCommand->getDefinition()->hasArgument('name'));
        self::assertSame('Specific task name(s) to retry', $retryFailedTaskCommand->getDefinition()->getArgument('name')->getDescription());
        self::assertTrue($retryFailedTaskCommand->getDefinition()->getArgument('name')->isRequired());
        self::assertTrue($retryFailedTaskCommand->getDefinition()->hasOption('force'));
        self::assertSame('Force the operation without confirmation', $retryFailedTaskCommand->getDefinition()->getOption('force')->getDescription());
        self::assertSame('f', $retryFailedTaskCommand->getDefinition()->getOption('force')->getShortcut());
        self::assertSame(
            $retryFailedTaskCommand->getHelp(),
            <<<'EOF'
                The <info>%command.name%</info> command retry a failed task.

                    <info>php %command.full_name%</info>

                Use the task-name argument to specify the task to retry:
                    <info>php %command.full_name% <task-name></info>

                Use the --force option to force the task retry without asking for confirmation:
                    <info>php %command.full_name% <task-name> --force</info>
                EOF
        );
    }

    public function testCommandCannotRetryUndefinedTask(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn(new TaskList());

        $retryFailedTaskCommand = new RetryFailedTaskCommand($worker, $eventDispatcher);
        $commandTester = new CommandTester($retryFailedTaskCommand);
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('The task "foo" does not fails', $commandTester->getDisplay());
    }

    public function testCommandCannotRetryTaskWithException(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);
        $worker->expects(self::once())->method('execute')->willThrowException(new Exception('Random execution error'));

        $retryFailedTaskCommand = new RetryFailedTaskCommand($worker, $eventDispatcher);
        $commandTester = new CommandTester($retryFailedTaskCommand);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('An error occurred when trying to retry the task:', $commandTester->getDisplay());
        self::assertStringContainsString('Random execution error', $commandTester->getDisplay());
    }

    public function testCommandCannotRetryTaskWithoutConfirmationOrForceOption(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);
        $worker->expects(self::never())->method('execute');

        $retryFailedTaskCommand = new RetryFailedTaskCommand($worker, $eventDispatcher);
        $commandTester = new CommandTester($retryFailedTaskCommand);
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] The task "foo" has not been retried', $commandTester->getDisplay());
    }

    public function testCommandCanRetryTaskWithForceOption(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')
            ->with(new StopWorkerOnTaskLimitSubscriber(1, $logger))
        ;

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);
        $worker->expects(self::once())->method('execute');

        $retryFailedTaskCommand = new RetryFailedTaskCommand($worker, $eventDispatcher, $logger);
        $commandTester = new CommandTester($retryFailedTaskCommand);
        $commandTester->execute([
            'name' => 'foo',
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The task "foo" has been retried', $commandTester->getDisplay());
    }

    public function testCommandCanRetryTask(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')
            ->with(new StopWorkerOnTaskLimitSubscriber(1, $logger))
        ;

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);
        $worker->expects(self::once())->method('execute');

        $retryFailedTaskCommand = new RetryFailedTaskCommand($worker, $eventDispatcher, $logger);
        $commandTester = new CommandTester($retryFailedTaskCommand);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('The task "foo" has been retried', $commandTester->getDisplay());
    }
}
