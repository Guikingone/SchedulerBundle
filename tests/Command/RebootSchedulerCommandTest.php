<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Worker\WorkerConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use SchedulerBundle\Command\RebootSchedulerCommand;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RebootSchedulerCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $rebootSchedulerCommand = new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher);

        self::assertSame('scheduler:reboot', $rebootSchedulerCommand->getName());
        self::assertSame('Reboot the scheduler', $rebootSchedulerCommand->getDescription());
        self::assertTrue($rebootSchedulerCommand->getDefinition()->hasOption('dry-run'));
        self::assertSame('d', $rebootSchedulerCommand->getDefinition()->getOption('dry-run')->getShortcut());
        self::assertSame('Test the reboot without executing the tasks, the "ready to reboot" tasks are displayed', $rebootSchedulerCommand->getDefinition()->getOption('dry-run')->getDescription());
        self::assertSame(
            $rebootSchedulerCommand->getHelp(),
            <<<'EOF'
                The <info>%command.name%</info> command reboot the scheduler.

                    <info>php %command.full_name%</info>

                Use the --dry-run option to list the tasks executed when the scheduler reboot:
                    <info>php %command.full_name% --dry-run</info>
                EOF
        );
    }

    public function testRebootCanSucceedOnHydratedTasksListButWithoutRebootTask(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('getIterator')->willReturn(new ArrayIterator([]));
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('execute')->with(self::equalTo(WorkerConfiguration::create()), ...$taskList);

        $commandTester = new CommandTester(new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] The scheduler have been rebooted, no tasks have been executed', $commandTester->getDisplay());
    }

    public function testRebootCanSucceedOnHydratedTasksListButWithoutRebootTaskOnDryRun(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::never())->method('getIterator');
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('execute');

        $commandTester = new CommandTester(new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher));
        $commandTester->execute([
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] The scheduler does not contain any tasks', $commandTester->getDisplay());
        self::assertStringContainsString('Be sure that the tasks use the "@reboot" expression', $commandTester->getDisplay());
    }

    /**
     * @group time-sensitive
     *
     * @throws Throwable
     */
    public function testCommandCannotRebootSchedulerWithRunningWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTaskLimitSubscriber(1, $logger));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('@reboot');
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('bar');
        $secondTask->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $secondTask->expects(self::never())->method('getState')->willReturn(TaskInterface::ENABLED);
        $secondTask->expects(self::never())->method('getTags')->willReturn(['app', 'slow']);

        $taskList = new TaskList([$task, $secondTask]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('reboot');
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::exactly(2))->method('isRunning')->willReturnOnConsecutiveCalls(true, false);
        $worker->expects(self::once())->method('execute')->with(WorkerConfiguration::create(), self::equalTo($task));

        $commandTester = new CommandTester(new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher, $logger));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] The scheduler cannot be rebooted as the worker is not available', $commandTester->getDisplay());
        self::assertStringContainsString('The process will be retried as soon as the worker is available', $commandTester->getDisplay());
        self::assertStringContainsString('[OK] The scheduler have been rebooted', $commandTester->getDisplay());
        self::assertStringContainsString('Name', $commandTester->getDisplay());
        self::assertStringContainsString('foo', $commandTester->getDisplay());
        self::assertStringNotContainsString('bar', $commandTester->getDisplay());
        self::assertStringContainsString('Type', $commandTester->getDisplay());
        self::assertStringContainsString('State', $commandTester->getDisplay());
        self::assertStringContainsString('enabled', $commandTester->getDisplay());
        self::assertStringContainsString('Tags', $commandTester->getDisplay());
        self::assertStringContainsString('app', $commandTester->getDisplay());
        self::assertStringContainsString('slow', $commandTester->getDisplay());
    }

    public function testRebootCanSucceedOnHydratedTasksListAndWithRebootTask(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::once())->method('addSubscriber');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('@reboot');
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('bar');
        $secondTask->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $secondTask->expects(self::never())->method('getState')->willReturn(TaskInterface::ENABLED);
        $secondTask->expects(self::never())->method('getTags')->willReturn(['app', 'slow']);

        $taskList = new TaskList([$task, $secondTask]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('reboot');
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with(WorkerConfiguration::create(), self::equalTo($task));

        $commandTester = new CommandTester(new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] The scheduler have been rebooted', $commandTester->getDisplay());
        self::assertStringContainsString('Name', $commandTester->getDisplay());
        self::assertStringContainsString('foo', $commandTester->getDisplay());
        self::assertStringNotContainsString('bar', $commandTester->getDisplay());
        self::assertStringContainsString('Type', $commandTester->getDisplay());
        self::assertStringContainsString('State', $commandTester->getDisplay());
        self::assertStringContainsString('enabled', $commandTester->getDisplay());
        self::assertStringContainsString('Tags', $commandTester->getDisplay());
        self::assertStringContainsString('app', $commandTester->getDisplay());
        self::assertStringContainsString('slow', $commandTester->getDisplay());
    }

    public function testCommandCanDryRunTheSchedulerReboot(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('@reboot');
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('bar');
        $secondTask->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $secondTask->expects(self::never())->method('getState')->willReturn(TaskInterface::ENABLED);
        $secondTask->expects(self::never())->method('getTags')->willReturn(['app', 'slow']);

        $taskList = new TaskList([$task, $secondTask]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('reboot');
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('isRunning');
        $worker->expects(self::never())->method('execute');

        $commandTester = new CommandTester(new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher));
        $commandTester->execute([
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] The following tasks will be executed when the scheduler will reboot:', $commandTester->getDisplay());
        self::assertStringContainsString('Name', $commandTester->getDisplay());
        self::assertStringContainsString('foo', $commandTester->getDisplay());
        self::assertStringNotContainsString('bar', $commandTester->getDisplay());
        self::assertStringContainsString('Type', $commandTester->getDisplay());
        self::assertStringContainsString('State', $commandTester->getDisplay());
        self::assertStringContainsString('enabled', $commandTester->getDisplay());
        self::assertStringContainsString('Tags', $commandTester->getDisplay());
        self::assertStringContainsString('app', $commandTester->getDisplay());
        self::assertStringContainsString('slow', $commandTester->getDisplay());
    }
}
