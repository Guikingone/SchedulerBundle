<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use SchedulerBundle\Command\ListTasksCommand;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ListTasksCommandTest extends TestCase
{
    public function testCommandIsCorrectlyConfigured(): void
    {
        $schedulerRegistry = $this->createMock(SchedulerInterface::class);
        $command = new ListTasksCommand($schedulerRegistry);

        self::assertSame('scheduler:list', $command->getName());
        self::assertSame('List the tasks', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('expression'));
        self::assertSame('The expression of the tasks', $command->getDefinition()->getOption('expression')->getDescription());
        self::assertNull($command->getDefinition()->getOption('expression')->getShortcut());
        self::assertTrue($command->getDefinition()->hasOption('state'));
        self::assertSame('The state of the tasks', $command->getDefinition()->getOption('state')->getDescription());
        self::assertSame('s', $command->getDefinition()->getOption('state')->getShortcut());
        self::assertSame(
            $command->getHelp(),
            <<<'EOF'
The <info>%command.name%</info> command list tasks.

    <info>php %command.full_name%</info>

Use the --expression option to list the tasks with a specific expression:
    <info>php %command.full_name% --expression=* * * * *</info>

Use the --state option to list the tasks with a specific state:
    <info>php %command.full_name% --state=paused</info>
EOF
        );
    }

    public function testCommandCannotReturnTaskOnEmptyScheduler(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('toArray')->willReturn([]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $command = new ListTasksCommand($scheduler);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:list'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    /**
     * @dataProvider provideStateOption
     */
    public function testCommandCanListTaskWithSpecificState(string $stateOption): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getDescription')->willReturn('A random task');
        $task->expects(self::exactly(2))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable());
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(1))->method('getName')->willReturn('bar');
        $secondTask->expects(self::never())->method('getDescription')->willReturn('A random task');
        $secondTask->expects(self::never())->method('getExpression')->willReturn('* * * * *');
        $secondTask->expects(self::never())->method('getLastExecution')->willReturn(new DateTimeImmutable());
        $secondTask->expects(self::once())->method('getState')->willReturn(TaskInterface::DISABLED);
        $secondTask->expects(self::never())->method('getTags')->willReturn(['app', 'slow']);

        $taskList = new TaskList([$task, $secondTask]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $command = new ListTasksCommand($scheduler);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:list'));
        $tester->execute([
            $stateOption => TaskInterface::ENABLED,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] 1 task found', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('foo', $tester->getDisplay());
        self::assertStringContainsString('Description', $tester->getDisplay());
        self::assertStringContainsString('A random task', $tester->getDisplay());
        self::assertStringContainsString('Expression', $tester->getDisplay());
        self::assertStringContainsString('* * * * *', $tester->getDisplay());
        self::assertStringContainsString('Last execution date', $tester->getDisplay());
        self::assertStringContainsString('Next execution date', $tester->getDisplay());
        self::assertStringContainsString('Last execution duration', $tester->getDisplay());
        self::assertStringContainsString('State', $tester->getDisplay());
        self::assertStringContainsString(TaskInterface::ENABLED, $tester->getDisplay());
        self::assertStringContainsString('Tags', $tester->getDisplay());
        self::assertStringContainsString('app, slow', $tester->getDisplay());
    }

    /**
     * @dataProvider provideExpressionOption
     */
    public function testCommandCanListTaskWithSpecificExpression(string $expressionOption): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getDescription')->willReturn('A random task');
        $task->expects(self::exactly(2))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable());
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::exactly(2))->method('toArray')->willReturn([$task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $command = new ListTasksCommand($scheduler);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:list'));
        $tester->execute([
            $expressionOption => '* * * * *',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] 1 task found', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('Description', $tester->getDisplay());
        self::assertStringContainsString('Expression', $tester->getDisplay());
        self::assertStringContainsString('Last execution date', $tester->getDisplay());
        self::assertStringContainsString('Next execution date', $tester->getDisplay());
        self::assertStringContainsString('Last execution duration', $tester->getDisplay());
        self::assertStringContainsString('State', $tester->getDisplay());
        self::assertStringContainsString('Tags', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithoutFilter(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getDescription')->willReturn('A random task');
        $task->expects(self::exactly(2))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable());
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::never())->method('filter');
        $taskList->expects(self::exactly(2))->method('toArray')->willReturn([$task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $command = new ListTasksCommand($scheduler);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:list'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] 1 task found', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('Description', $tester->getDisplay());
        self::assertStringContainsString('Expression', $tester->getDisplay());
        self::assertStringContainsString('Last execution date', $tester->getDisplay());
        self::assertStringContainsString('Next execution date', $tester->getDisplay());
        self::assertStringContainsString('Last execution duration', $tester->getDisplay());
        self::assertStringContainsString('State', $tester->getDisplay());
        self::assertStringContainsString('Tags', $tester->getDisplay());
    }

    /**
     * @dataProvider provideOptions
     */
    public function testCommandCanReturnTasksWithStateAndExpressionFilter(string $expressionOption, string $stateOption): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getDescription')->willReturn('A random task');
        $task->expects(self::exactly(2))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable());
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $secondTasks = $this->createMock(TaskInterface::class);
        $secondTasks->expects(self::once())->method('getName')->willReturn('bar');
        $secondTasks->expects(self::once())->method('getDescription')->willReturn('A second random task');
        $secondTasks->expects(self::exactly(2))->method('getExpression')->willReturn('* * * * *');
        $secondTasks->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable());
        $secondTasks->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $secondTasks->expects(self::once())->method('getTags')->willReturn(['app', 'fast']);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::exactly(2))->method('filter')->willReturnSelf();
        $taskList->expects(self::exactly(2))->method('toArray')->willReturn([$task, $secondTasks]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $command = new ListTasksCommand($scheduler);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:list'));
        $tester->execute([
            $expressionOption => '* * * * *',
            $stateOption => TaskInterface::ENABLED,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] 2 tasks found', $tester->getDisplay());
        self::assertStringContainsString('Name', $tester->getDisplay());
        self::assertStringContainsString('foo', $tester->getDisplay());
        self::assertStringContainsString('bar', $tester->getDisplay());
        self::assertStringContainsString('Description', $tester->getDisplay());
        self::assertStringContainsString('A random task', $tester->getDisplay());
        self::assertStringContainsString('A second random task', $tester->getDisplay());
        self::assertStringContainsString('Expression', $tester->getDisplay());
        self::assertStringContainsString('* * * * *', $tester->getDisplay());
        self::assertStringContainsString('* * * * *', $tester->getDisplay());
        self::assertStringContainsString('Last execution date', $tester->getDisplay());
        self::assertStringContainsString('Next execution date', $tester->getDisplay());
        self::assertStringContainsString('Last execution duration', $tester->getDisplay());
        self::assertStringContainsString('Last execution memory usage', $tester->getDisplay());
        self::assertStringContainsString('State', $tester->getDisplay());
        self::assertStringContainsString('Tags', $tester->getDisplay());
        self::assertStringContainsString('app', $tester->getDisplay());
        self::assertStringContainsString('slow', $tester->getDisplay());
        self::assertStringContainsString('app', $tester->getDisplay());
        self::assertStringContainsString('fast', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithInvalidExpressionFilter(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturn(new TaskList());
        $taskList->expects(self::once())->method('toArray')->willReturn([$task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $command = new ListTasksCommand($scheduler);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:list'));
        $tester->execute([
            '--expression' => '0 * * * *',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithInvalidStateFilter(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturn(new TaskList());
        $taskList->expects(self::once())->method('toArray')->willReturn([$task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $command = new ListTasksCommand($scheduler);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:list'));
        $tester->execute([
            '--state' => 'test',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithInvalidStateAndExpressionFilter(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturn(new TaskList());
        $taskList->expects(self::once())->method('toArray')->willReturn([$task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $command = new ListTasksCommand($scheduler);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->get('scheduler:list'));
        $tester->execute([
            '--expression' => '0 * * * *',
            '--state' => 'started',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    public function provideStateOption(): Generator
    {
        yield ['--state'];
        yield ['-s'];
    }

    public function provideExpressionOption(): Generator
    {
        yield ['--expression'];
    }

    public function provideOptions(): Generator
    {
        yield ['--expression', '--state'];
    }
}
