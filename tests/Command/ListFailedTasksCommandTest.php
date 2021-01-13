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

        static::assertSame('scheduler:list:failed', $command->getName());
        static::assertSame('List all the failed tasks', $command->getDescription());
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

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('No failed task has been found', $tester->getDisplay());
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

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('1 task found', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('foo.failed', $tester->getDisplay());
        static::assertStringContainsString('Expression', $tester->getDisplay());
        static::assertStringContainsString('* * * * *', $tester->getDisplay());
        static::assertStringContainsString('Reason', $tester->getDisplay());
        static::assertStringContainsString('Foo error occurred', $tester->getDisplay());
        static::assertStringContainsString('Date', $tester->getDisplay());
    }
}
