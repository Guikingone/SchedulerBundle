<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\TaskList;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use SchedulerBundle\Command\ListFailedTasksCommand;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ListFailedTasksCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $listFailedTasksCommand = new ListFailedTasksCommand($worker);

        self::assertSame('scheduler:list:failed', $listFailedTasksCommand->getName());
        self::assertSame('List all the failed tasks', $listFailedTasksCommand->getDescription());
    }

    public function testCommandCannotListEmptyFailedTasks(): void
    {
        $failedTaskList = new TaskList();

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($failedTaskList);

        $listFailedTasksCommand = new ListFailedTasksCommand($worker);

        $application = new Application();
        $application->add($listFailedTasksCommand);

        $commandTester = new CommandTester($application->get('scheduler:list:failed'));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('No failed task has been found', $commandTester->getDisplay());
    }

    public function testCommandCanListFailedTasks(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');

        $failedTask = new FailedTask($task, 'Foo error occurred');

        $failedTaskList = new TaskList([$failedTask]);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($failedTaskList);

        $listFailedTasksCommand = new ListFailedTasksCommand($worker);

        $application = new Application();
        $application->add($listFailedTasksCommand);

        $commandTester = new CommandTester($application->get('scheduler:list:failed'));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('1 task found', $commandTester->getDisplay());
        self::assertStringContainsString('Name', $commandTester->getDisplay());
        self::assertStringContainsString('foo.failed', $commandTester->getDisplay());
        self::assertStringContainsString('Expression', $commandTester->getDisplay());
        self::assertStringContainsString('* * * * *', $commandTester->getDisplay());
        self::assertStringContainsString('Reason', $commandTester->getDisplay());
        self::assertStringContainsString('Foo error occurred', $commandTester->getDisplay());
        self::assertStringContainsString('Date', $commandTester->getDisplay());
    }
}
