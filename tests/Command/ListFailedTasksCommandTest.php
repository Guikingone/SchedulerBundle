<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use SchedulerBundle\Command\ListFailedTasksCommand;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ListFailedTasksCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $command = new ListFailedTasksCommand($worker);

        self::assertSame('scheduler:list:failed', $command->getName());
        self::assertSame('List all the failed tasks', $command->getDescription());
    }

    public function testCommandCannotListEmptyFailedTasks(): void
    {
        $failedTasks = $this->createMock(TaskListInterface::class);
        $failedTasks->expects(self::once())->method('toArray')->willReturn([]);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($failedTasks);

        $command = new ListFailedTasksCommand($worker);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:list:failed'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No failed task has been found', $tester->getDisplay());
    }

    public function testCommandCanListFailedTasks(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');

        $failedTask = new FailedTask($task, 'Foo error occurred');

        $failedTasks = $this->createMock(TaskListInterface::class);
        $failedTasks->expects(self::once())->method('toArray')->willReturn([$failedTask]);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($failedTasks);

        $command = new ListFailedTasksCommand($worker);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:list:failed'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 task found', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('foo.failed', $tester->getDisplay());
        self::assertStringContainsString('Expression', $tester->getDisplay());
        self::assertStringContainsString('* * * * *', $tester->getDisplay());
        self::assertStringContainsString('Reason', $tester->getDisplay());
        self::assertStringContainsString('Foo error occurred', $tester->getDisplay());
        self::assertStringContainsString('Date', $tester->getDisplay());
    }
}
