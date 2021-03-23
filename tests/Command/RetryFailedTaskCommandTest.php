<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
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

        $command = new RetryFailedTaskCommand($worker, $eventDispatcher);

        self::assertSame('scheduler:retry:failed', $command->getName());
        self::assertSame('Retries one or more tasks from the failed tasks', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasArgument('name'));
        self::assertSame('Specific task name(s) to retry', $command->getDefinition()->getArgument('name')->getDescription());
        self::assertTrue($command->getDefinition()->getArgument('name')->isRequired());
        self::assertTrue($command->getDefinition()->hasOption('force'));
        self::assertSame('Force the operation without confirmation', $command->getDefinition()->getOption('force')->getDescription());
        self::assertSame('f', $command->getDefinition()->getOption('force')->getShortcut());
        self::assertSame(
            $command->getHelp(),
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

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn(null);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RetryFailedTaskCommand($worker, $eventDispatcher);
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('The task "foo" does not fails', $tester->getDisplay());
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

        $command = new RetryFailedTaskCommand($worker, $eventDispatcher);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('An error occurred when trying to retry the task:', $tester->getDisplay());
        self::assertStringContainsString('Random execution error', $tester->getDisplay());
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

        $command = new RetryFailedTaskCommand($worker, $eventDispatcher);
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('[WARNING] The task "foo" has not been retried', $tester->getDisplay());
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

        $command = new RetryFailedTaskCommand($worker, $eventDispatcher, $logger);
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'foo',
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The task "foo" has been retried', $tester->getDisplay());
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

        $command = new RetryFailedTaskCommand($worker, $eventDispatcher, $logger);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([
            'name' => 'foo',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('The task "foo" has been retried', $tester->getDisplay());
    }
}
