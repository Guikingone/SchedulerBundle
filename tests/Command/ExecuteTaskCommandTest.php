<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Command\ExecuteTaskCommand;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecuteTaskCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker);

        self::assertSame('scheduler:execute', $command->getName());
        self::assertSame('Execute tasks (due or not) depending on filters', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('due'));
        self::assertSame('d', $command->getDefinition()->getOption('due')->getShortcut());
        self::assertFalse($command->getDefinition()->getOption('due')->acceptValue());
        self::assertSame('Define if the filters must be applied on due tasks', $command->getDefinition()->getOption('due')->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('name'));
        self::assertNull($command->getDefinition()->getOption('name')->getShortcut());
        self::assertTrue($command->getDefinition()->getOption('name')->isValueOptional());
        self::assertTrue($command->getDefinition()->getOption('name')->isArray());
        self::assertSame('The name of the task(s) to execute', $command->getDefinition()->getOption('name')->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('expression'));
        self::assertNull($command->getDefinition()->getOption('expression')->getShortcut());
        self::assertTrue($command->getDefinition()->getOption('expression')->isValueOptional());
        self::assertTrue($command->getDefinition()->getOption('expression')->isArray());
        self::assertSame('The expression of the task(s) to execute', $command->getDefinition()->getOption('expression')->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('tags'));
        self::assertSame('t', $command->getDefinition()->getOption('tags')->getShortcut());
        self::assertTrue($command->getDefinition()->getOption('tags')->isValueOptional());
        self::assertTrue($command->getDefinition()->getOption('tags')->isArray());
        self::assertSame('The tags of the task(s) to execute', $command->getDefinition()->getOption('tags')->getDescription());
        self::assertSame(
            $command->getHelp(),
            <<<'EOF'
                The <info>%command.name%</info> command execute tasks.

                    <info>php %command.full_name%</info>

                Use the --due option to execute the due tasks:
                    <info>php %command.full_name% --due</info>

                Use the --name option to filter the executed tasks depending on their name:
                    <info>php %command.full_name% --name=foo, bar</info>

                Use the --expression option to filter the executed tasks depending on their expression:
                    <info>php %command.full_name% --expression=* * * * *</info>

                Use the --tags option to filter the executed tasks depending on their tags:
                    <info>php %command.full_name% --tags=foo, bar</info>
                EOF
        );
    }

    public function testCommandCannotExecuteDueTasks(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList());

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->execute([
            '--due' => true,
        ]);

        self::assertStringContainsString('[WARNING] No tasks to execute found', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testCommandCannotExecuteWholeTasksList(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList());
        $scheduler->expects(self::never())->method('getDueTasks');

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('[WARNING] No tasks to execute found', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    public function testCommandCanExecuteDueTasksWithSpecificName(): void
    {
        $task = new NullTask('foo');
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new StopWorkerOnTaskLimitSubscriber(1, $logger));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker, $logger);
        $tester = new CommandTester($command);
        $tester->execute([
            '--due' => true,
            '--name' => ['foo'],
        ]);

        self::assertStringContainsString('[INFO] Found 1 task', $tester->getDisplay());
        self::assertStringNotContainsString('[WARNING] No tasks to execute found', $tester->getDisplay());
        self::assertStringContainsString('[INFO] The tasks following the listed conditions will be executed:', $tester->getDisplay());
        self::assertStringContainsString('- Task(s) with the following name(s): foo', $tester->getDisplay());
        self::assertStringContainsString('1 task to be executed', $tester->getDisplay());
        self::assertStringContainsString('[OK] 1 task has been executed', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    public function testCommandCanExecuteMultipleDueTasksWithSpecificName(): void
    {
        $task = new NullTask('foo');
        $secondTask = new NullTask('bar');
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new StopWorkerOnTaskLimitSubscriber(2, $logger));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($task), self::equalTo($secondTask));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task, $secondTask]));

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker, $logger);
        $tester = new CommandTester($command);
        $tester->execute([
            '--due' => true,
            '--name' => ['foo', 'bar'],
        ]);

        self::assertStringContainsString('[INFO] Found 2 tasks', $tester->getDisplay());
        self::assertStringNotContainsString('[WARNING] No tasks to execute found', $tester->getDisplay());
        self::assertStringContainsString('[INFO] The tasks following the listed conditions will be executed:', $tester->getDisplay());
        self::assertStringContainsString('- Task(s) with the following name(s): foo', $tester->getDisplay());
        self::assertStringContainsString('2 tasks to be executed', $tester->getDisplay());
        self::assertStringContainsString('[OK] 2 tasks have been executed', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    public function testCommandCanExecuteWholeTasksListWithSpecificName(): void
    {
        $task = new NullTask('foo');
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new StopWorkerOnTaskLimitSubscriber(1, $logger));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getDueTasks');
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([$task]));

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker, $logger);
        $tester = new CommandTester($command);
        $tester->execute([
            '--name' => ['foo'],
        ]);

        self::assertStringContainsString('[INFO] Found 1 task', $tester->getDisplay());
        self::assertStringNotContainsString('[WARNING] No tasks to execute found', $tester->getDisplay());
        self::assertStringContainsString('[INFO] The tasks following the listed conditions will be executed:', $tester->getDisplay());
        self::assertStringContainsString('- Task(s) with the following name(s): foo', $tester->getDisplay());
        self::assertStringContainsString('1 task to be executed', $tester->getDisplay());
        self::assertStringContainsString('[OK] 1 task has been executed', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    public function testCommandCanExecuteWholeTasksListWithMultipleTasksWithSpecificName(): void
    {
        $task = new NullTask('foo');
        $secondTask = new NullTask('bar');
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new StopWorkerOnTaskLimitSubscriber(2, $logger));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($task), self::equalTo($secondTask));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getDueTasks');
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([$task, $secondTask]));

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker, $logger);
        $tester = new CommandTester($command);
        $tester->execute([
            '--name' => ['foo', 'bar'],
        ]);

        self::assertStringContainsString('[INFO] Found 2 tasks', $tester->getDisplay());
        self::assertStringNotContainsString('[WARNING] No tasks to execute found', $tester->getDisplay());
        self::assertStringContainsString('[INFO] The tasks following the listed conditions will be executed:', $tester->getDisplay());
        self::assertStringContainsString('- Task(s) with the following name(s): foo', $tester->getDisplay());
        self::assertStringContainsString('2 tasks to be executed', $tester->getDisplay());
        self::assertStringContainsString('[OK] 2 tasks have been executed', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    public function testCommandCanExecuteDueTasksWithSpecificExpression(): void
    {
        $task = new NullTask('foo');
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new StopWorkerOnTaskLimitSubscriber(1, $logger));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker, $logger);
        $tester = new CommandTester($command);
        $tester->execute([
            '--due' => true,
            '--expression' => ['* * * * *'],
        ]);

        self::assertStringContainsString('[INFO] Found 1 task', $tester->getDisplay());
        self::assertStringNotContainsString('[WARNING] No tasks to execute found', $tester->getDisplay());
        self::assertStringContainsString('[INFO] The tasks following the listed conditions will be executed:', $tester->getDisplay());
        self::assertStringContainsString('- Task(s) with the following expression(s): * * * * *', $tester->getDisplay());
        self::assertStringContainsString('1 task to be executed', $tester->getDisplay());
        self::assertStringContainsString('1 task has been executed', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    public function testCommandCanExecuteWholeTasksListWithSpecificExpression(): void
    {
        $task = new NullTask('foo');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getDueTasks');
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([$task]));

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->execute([
            '--expression' => ['* * * * *'],
        ]);

        self::assertStringContainsString('[INFO] Found 1 task', $tester->getDisplay());
        self::assertStringNotContainsString('[WARNING] No tasks to execute found', $tester->getDisplay());
        self::assertStringContainsString('[INFO] The tasks following the listed conditions will be executed:', $tester->getDisplay());
        self::assertStringContainsString('- Task(s) with the following expression(s): * * * * *', $tester->getDisplay());
        self::assertStringContainsString('1 task to be executed', $tester->getDisplay());
        self::assertStringContainsString('1 task has been executed', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    public function testCommandCanExecuteDueTasksWithSpecificTags(): void
    {
        $task = new NullTask('foo', [
            'tags' => ['@reboot', '@deploy'],
        ]);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->execute([
            '--due' => true,
            '--tags' => ['@reboot', '@deploy'],
        ]);

        self::assertStringContainsString('[INFO] Found 1 task', $tester->getDisplay());
        self::assertStringNotContainsString('[WARNING] No tasks to execute found', $tester->getDisplay());
        self::assertStringContainsString('[INFO] The tasks following the listed conditions will be executed:', $tester->getDisplay());
        self::assertStringContainsString('- Task(s) with the following tags(s): @reboot, @deploy', $tester->getDisplay());
        self::assertStringContainsString('1 task to be executed', $tester->getDisplay());
        self::assertStringContainsString('1 task has been executed', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    public function testCommandCanExecuteWholeTasksListWithSpecificTags(): void
    {
        $task = new NullTask('foo', [
            'tags' => ['@reboot', '@deploy'],
        ]);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($task));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getDueTasks');
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([$task]));

        $command = new ExecuteTaskCommand($eventDispatcher, $scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->execute([
            '--tags' => ['@reboot', '@deploy'],
        ]);

        self::assertStringContainsString('[INFO] Found 1 task', $tester->getDisplay());
        self::assertStringNotContainsString('[WARNING] No tasks to execute found', $tester->getDisplay());
        self::assertStringContainsString('[INFO] The tasks following the listed conditions will be executed:', $tester->getDisplay());
        self::assertStringContainsString('- Task(s) with the following tags(s): @reboot, @deploy', $tester->getDisplay());
        self::assertStringContainsString('1 task to be executed', $tester->getDisplay());
        self::assertStringContainsString('1 task has been executed', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
