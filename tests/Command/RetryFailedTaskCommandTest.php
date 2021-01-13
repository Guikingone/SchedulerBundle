<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use SchedulerBundle\Command\RetryFailedTaskCommand;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class RetryFailedTaskCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $command = new RetryFailedTaskCommand($scheduler, $worker, $eventDispatcher);

        static::assertSame('scheduler:retry:failed', $command->getName());
        static::assertSame('Retries one or more tasks from the failed tasks', $command->getDescription());
        static::assertTrue($command->getDefinition()->hasArgument('name'));
        static::assertSame('Specific task name(s) to retry', $command->getDefinition()->getArgument('name')->getDescription());
        static::assertTrue($command->getDefinition()->getArgument('name')->isRequired());
        static::assertTrue($command->getDefinition()->hasOption('force'));
        static::assertSame('Force the operation without confirmation', $command->getDefinition()->getOption('force')->getDescription());
        static::assertSame('f', $command->getDefinition()->getOption('force')->getShortcut());
        static::assertSame($command->getHelp(), <<<'EOF'
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
        $scheduler = $this->createMock(SchedulerInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn(null);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RetryFailedTaskCommand($scheduler, $worker, $eventDispatcher);
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'foo',
        ]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('The task "foo" does not fails', $tester->getDisplay());
    }

    public function testCommandCannotRetryTaskWithException(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);
        $worker->expects(self::once())->method('execute')->willThrowException(new \Exception('Random execution error'));

        $command = new RetryFailedTaskCommand($scheduler, $worker, $eventDispatcher);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([
            'name' => 'foo',
        ]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('An error occurred when trying to retry the task:', $tester->getDisplay());
        static::assertStringContainsString('Random execution error', $tester->getDisplay());
    }

    public function testCommandCanRetryTaskWithForceOption(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);
        $worker->expects(self::once())->method('execute');

        $command = new RetryFailedTaskCommand($scheduler, $worker, $eventDispatcher);
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'foo',
            '--force' => true,
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('The task "foo" has been retried', $tester->getDisplay());
    }

    public function testCommandCanRetryTask(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);
        $worker->expects(self::once())->method('execute');

        $command = new RetryFailedTaskCommand($scheduler, $worker, $eventDispatcher);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([
            'name' => 'foo',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('The task "foo" has been retried', $tester->getDisplay());
    }
}
