<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\TaskList;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use SchedulerBundle\Command\RebootSchedulerCommand;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RebootSchedulerCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $command = new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher);

        self::assertSame('scheduler:reboot', $command->getName());
        self::assertSame('Reboot the scheduler', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('dry-run'));
        self::assertSame('d', $command->getDefinition()->getOption('dry-run')->getShortcut());
        self::assertSame('Test the reboot without executing the tasks, the "ready to reboot" tasks are displayed', $command->getDefinition()->getOption('dry-run')->getDescription());
        self::assertSame(
            $command->getHelp(),
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
        $worker->expects(self::never())->method('execute')->with(self::equalTo([]), ...$taskList);

        $tester = new CommandTester(new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] The scheduler have been rebooted, no tasks have been executed', $tester->getDisplay());
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

        $tester = new CommandTester(new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher));
        $tester->execute([
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[WARNING] The scheduler does not contain any tasks planned for the reboot process', $tester->getDisplay());
    }

    public function testCommandCannotRebootSchedulerWithRunningWorker(): void
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
        $worker->expects(self::exactly(2))->method('isRunning')->willReturnOnConsecutiveCalls(true, false);
        $worker->expects(self::once())->method('execute')->with([], self::equalTo($task));

        $tester = new CommandTester(new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[WARNING] The scheduler cannot be rebooted as the worker is not available, retrying to access it', $tester->getDisplay());
        self::assertStringContainsString('[OK] The scheduler have been rebooted', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('foo', $tester->getDisplay());
        self::assertStringNotContainsString('bar', $tester->getDisplay());
        self::assertStringContainsString('Type', $tester->getDisplay());
        self::assertStringContainsString('State', $tester->getDisplay());
        self::assertStringContainsString('enabled', $tester->getDisplay());
        self::assertStringContainsString('Tags', $tester->getDisplay());
        self::assertStringContainsString('app', $tester->getDisplay());
        self::assertStringContainsString('slow', $tester->getDisplay());
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
        $worker->expects(self::once())->method('execute')->with([], self::equalTo($task));

        $tester = new CommandTester(new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] The scheduler have been rebooted', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('foo', $tester->getDisplay());
        self::assertStringNotContainsString('bar', $tester->getDisplay());
        self::assertStringContainsString('Type', $tester->getDisplay());
        self::assertStringContainsString('State', $tester->getDisplay());
        self::assertStringContainsString('enabled', $tester->getDisplay());
        self::assertStringContainsString('Tags', $tester->getDisplay());
        self::assertStringContainsString('app', $tester->getDisplay());
        self::assertStringContainsString('slow', $tester->getDisplay());
    }

    public function testCommandCanDryRunTheSchedulerReboot(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

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

        $tester = new CommandTester(new RebootSchedulerCommand($scheduler, $worker, $eventDispatcher));
        $tester->execute([
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] The following tasks will be executed when the scheduler will reboot:', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('foo', $tester->getDisplay());
        self::assertStringNotContainsString('bar', $tester->getDisplay());
        self::assertStringContainsString('Type', $tester->getDisplay());
        self::assertStringContainsString('State', $tester->getDisplay());
        self::assertStringContainsString('enabled', $tester->getDisplay());
        self::assertStringContainsString('Tags', $tester->getDisplay());
        self::assertStringContainsString('app', $tester->getDisplay());
        self::assertStringContainsString('slow', $tester->getDisplay());
    }
}
