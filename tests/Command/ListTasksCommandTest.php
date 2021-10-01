<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\ChainedTask;
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
        $listTasksCommand = new ListTasksCommand($schedulerRegistry);

        self::assertSame('scheduler:list', $listTasksCommand->getName());
        self::assertSame('List the tasks', $listTasksCommand->getDescription());
        self::assertTrue($listTasksCommand->getDefinition()->hasOption('expression'));
        self::assertSame('The expression of the tasks', $listTasksCommand->getDefinition()->getOption('expression')->getDescription());
        self::assertNull($listTasksCommand->getDefinition()->getOption('expression')->getShortcut());
        self::assertTrue($listTasksCommand->getDefinition()->hasOption('state'));
        self::assertSame('The state of the tasks', $listTasksCommand->getDefinition()->getOption('state')->getDescription());
        self::assertSame('s', $listTasksCommand->getDefinition()->getOption('state')->getShortcut());
        self::assertSame(
            $listTasksCommand->getHelp(),
            <<<'EOF'
                The <info>%command.name%</info> command list tasks.

                    <info>php %command.full_name%</info>

                Use the --expression option to list the tasks with a specific expression:
                    <info>php %command.full_name% --expression=* * * * *</info>

                Use the --state option to list the tasks with a specific state:
                    <info>php %command.full_name% --state=paused</info>

                Use the -s option to list the tasks with a specific state:
                    <info>php %command.full_name% -s=paused</info>
                EOF
        );
    }

    public function testCommandCannotReturnTaskOnEmptyScheduler(): void
    {
        $taskList = new TaskList();

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $commandTester = new CommandTester(new ListTasksCommand($scheduler));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] No tasks found', $commandTester->getDisplay());
    }

    public function testCommandCannotReturnTaskOnEmptyTaskList(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $commandTester = new CommandTester(new ListTasksCommand($scheduler));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] No tasks found', $commandTester->getDisplay());
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
        $task->expects(self::once())->method('getLastExecution')->willReturn(new DateTimeImmutable());
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

        $commandTester = new CommandTester(new ListTasksCommand($scheduler));
        $commandTester->execute([
            $stateOption => TaskInterface::ENABLED,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] 1 task found', $commandTester->getDisplay());
        self::assertStringContainsString('Type', $commandTester->getDisplay());
        self::assertStringContainsString('Mock_TaskInterface_', $commandTester->getDisplay());
        self::assertStringContainsString('Name', $commandTester->getDisplay());
        self::assertStringContainsString('foo', $commandTester->getDisplay());
        self::assertStringContainsString('Description', $commandTester->getDisplay());
        self::assertStringContainsString('A random task', $commandTester->getDisplay());
        self::assertStringContainsString('Expression', $commandTester->getDisplay());
        self::assertStringContainsString('* * * * *', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution date', $commandTester->getDisplay());
        self::assertStringContainsString('Next execution date', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution duration', $commandTester->getDisplay());
        self::assertStringContainsString('Not tracked', $commandTester->getDisplay());
        self::assertStringContainsString('State', $commandTester->getDisplay());
        self::assertStringContainsString(TaskInterface::ENABLED, $commandTester->getDisplay());
        self::assertStringContainsString('Tags', $commandTester->getDisplay());
        self::assertStringContainsString('app, slow', $commandTester->getDisplay());
    }

    /**
     * @group time-sensitive
     */
    public function testCommandCanListTaskWithSubtasks(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getDescription')->willReturn('A foo task');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable('08/20/2020'));
        $task->expects(self::exactly(2))->method('getExecutionComputationTime')->willReturn(5002.0);
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getName')->willReturn('bar');
        $secondTask->expects(self::once())->method('getDescription')->willReturn('A bar task');
        $secondTask->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $secondTask->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable('08/20/2020'));
        $secondTask->expects(self::exactly(2))->method('getExecutionComputationTime')->willReturn(1002.0);
        $secondTask->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $secondTask->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $thirdTask = $this->createMock(TaskInterface::class);
        $thirdTask->expects(self::exactly(2))->method('getName')->willReturn('random');
        $thirdTask->expects(self::once())->method('getDescription')->willReturn('A bar task');
        $thirdTask->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $thirdTask->expects(self::once())->method('getLastExecution')->willReturn(null);
        $thirdTask->expects(self::exactly(2))->method('getExecutionComputationTime')->willReturn(1452.0);
        $thirdTask->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $thirdTask->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $chainedTask = new ChainedTask('nested');
        $chainedTask->setDescription('A nested task');
        $chainedTask->setLastExecution(new DateTimeImmutable('08/20/2020'));
        $chainedTask->setExecutionComputationTime(6002.0);
        $chainedTask->setTasks(new TaskList([$secondTask, $task, $thirdTask]));

        $taskList = new TaskList([$chainedTask]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $commandTester = new CommandTester(new ListTasksCommand($scheduler));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] 1 task found', $commandTester->getDisplay());
        self::assertStringContainsString('Type', $commandTester->getDisplay());
        self::assertStringContainsString('ChainedTask', $commandTester->getDisplay());
        self::assertStringContainsString('Name', $commandTester->getDisplay());
        self::assertStringContainsString('nested', $commandTester->getDisplay());
        self::assertStringContainsString('foo', $commandTester->getDisplay());
        self::assertStringContainsString('bar', $commandTester->getDisplay());
        self::assertStringContainsString('random', $commandTester->getDisplay());
        self::assertStringContainsString('Description', $commandTester->getDisplay());
        self::assertStringContainsString('A nested task', $commandTester->getDisplay());
        self::assertStringContainsString('6 secs', $commandTester->getDisplay());
        self::assertStringContainsString('          >', $commandTester->getDisplay());
        self::assertStringContainsString('A foo task', $commandTester->getDisplay());
        self::assertStringContainsString('5 secs', $commandTester->getDisplay());
        self::assertStringContainsString('A bar task', $commandTester->getDisplay());
        self::assertStringContainsString('1 sec', $commandTester->getDisplay());
        self::assertStringContainsString('1 sec', $commandTester->getDisplay());
        self::assertStringContainsString('Expression', $commandTester->getDisplay());
        self::assertStringContainsString('* * * * *', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution date', $commandTester->getDisplay());
        self::assertStringContainsString('2020-08-20T00:00:00+00:00', $commandTester->getDisplay());
        self::assertStringContainsString('Not executed', $commandTester->getDisplay());
        self::assertStringContainsString('Next execution date', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution duration', $commandTester->getDisplay());
        self::assertStringContainsString('Not tracked', $commandTester->getDisplay());
        self::assertStringContainsString('State', $commandTester->getDisplay());
        self::assertStringContainsString(TaskInterface::ENABLED, $commandTester->getDisplay());
        self::assertStringContainsString('Tags', $commandTester->getDisplay());
        self::assertStringContainsString('app, slow', $commandTester->getDisplay());
        self::assertStringContainsString('| Type        | Name   | Description   | Expression | Last execution date      ', $commandTester->getDisplay());
        self::assertStringContainsString('| ChainedTask | nested | A nested task | * * * * *  | 2020-08-20T00:00:00+00:00', $commandTester->getDisplay());
        self::assertStringContainsString('|           > | bar    | A bar task    | -          | 2020-08-20T00:00:00+00:00', $commandTester->getDisplay());
        self::assertStringContainsString('|           > | foo    | A foo task    | -          | 2020-08-20T00:00:00+00:00', $commandTester->getDisplay());
        self::assertStringContainsString('|           > | random | A bar task    | -          | Not executed             ', $commandTester->getDisplay());
        self::assertStringContainsString('| Last execution duration | Last execution memory usage | State   | Tags      |', $commandTester->getDisplay());
        self::assertStringContainsString('| 6 secs                  | Not tracked                 | enabled |           |', $commandTester->getDisplay());
        self::assertStringContainsString('| 1 sec                   | Not tracked                 | enabled | app, slow |', $commandTester->getDisplay());
        self::assertStringContainsString('| 5 secs                  | Not tracked                 | enabled | app, slow |', $commandTester->getDisplay());
        self::assertStringContainsString('| 1 sec                   | Not tracked                 | enabled | app, slow |', $commandTester->getDisplay());
    }

    public function testCommandCanListTaskWithSpecificExpression(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getDescription')->willReturn('A random task');
        $task->expects(self::exactly(3))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getLastExecution')->willReturn(new DateTimeImmutable());
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('bar');
        $secondTask->expects(self::never())->method('getDescription')->willReturn('A random task');
        $secondTask->expects(self::once())->method('getExpression')->willReturn('@reboot');
        $secondTask->expects(self::never())->method('getLastExecution')->willReturn(new DateTimeImmutable());
        $secondTask->expects(self::never())->method('getState')->willReturn(TaskInterface::ENABLED);
        $secondTask->expects(self::never())->method('getTags')->willReturn(['app', 'slow']);

        $taskList = new TaskList([$task, $secondTask]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $commandTester = new CommandTester(new ListTasksCommand($scheduler));
        $commandTester->execute([
            '--expression' => '* * * * *',
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] 1 task found', $commandTester->getDisplay());
        self::assertStringContainsString('Name', $commandTester->getDisplay());
        self::assertStringContainsString('Description', $commandTester->getDisplay());
        self::assertStringContainsString('Expression', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution date', $commandTester->getDisplay());
        self::assertStringContainsString('Next execution date', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution duration', $commandTester->getDisplay());
        self::assertStringContainsString('State', $commandTester->getDisplay());
        self::assertStringContainsString('Tags', $commandTester->getDisplay());
    }

    public function testCommandCanReturnTasksWithoutFilter(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getDescription')->willReturn('A random task');
        $task->expects(self::exactly(2))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getLastExecution')->willReturn(new DateTimeImmutable('08/20/2020'));
        $task->expects(self::exactly(2))->method('getExecutionComputationTime')->willReturn(1002.0);
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $taskList = new TaskList([$task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $commandTester = new CommandTester(new ListTasksCommand($scheduler));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] 1 task found', $commandTester->getDisplay());
        self::assertStringContainsString('Name', $commandTester->getDisplay());
        self::assertStringContainsString('Description', $commandTester->getDisplay());
        self::assertStringContainsString('Expression', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution date', $commandTester->getDisplay());
        self::assertStringContainsString('1 sec', $commandTester->getDisplay());
        self::assertStringContainsString('Next execution date', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution duration', $commandTester->getDisplay());
        self::assertStringContainsString('2020-08-20T00:00:00+00:00', $commandTester->getDisplay());
        self::assertStringContainsString('State', $commandTester->getDisplay());
        self::assertStringContainsString('Tags', $commandTester->getDisplay());
        self::assertStringContainsString('app, slow', $commandTester->getDisplay());
    }

    /**
     * @dataProvider provideOptions
     */
    public function testCommandCanReturnTasksWithStateAndExpressionFilter(string $expressionOption, string $stateOption): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(4))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getDescription')->willReturn('A random task');
        $task->expects(self::exactly(3))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getLastExecution')->willReturn(new DateTimeImmutable());
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(4))->method('getName')->willReturn('bar');
        $secondTask->expects(self::once())->method('getDescription')->willReturn('A second random task');
        $secondTask->expects(self::exactly(3))->method('getExpression')->willReturn('* * * * *');
        $secondTask->expects(self::once())->method('getLastExecution')->willReturn(new DateTimeImmutable());
        $secondTask->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $secondTask->expects(self::once())->method('getTags')->willReturn(['app', 'fast']);

        $taskList = new TaskList([$task, $secondTask]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $commandTester = new CommandTester(new ListTasksCommand($scheduler));
        $commandTester->execute([
            $expressionOption => '* * * * *',
            $stateOption => TaskInterface::ENABLED,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[OK] 2 tasks found', $commandTester->getDisplay());
        self::assertStringContainsString('Name', $commandTester->getDisplay());
        self::assertStringContainsString('foo', $commandTester->getDisplay());
        self::assertStringContainsString('bar', $commandTester->getDisplay());
        self::assertStringContainsString('Description', $commandTester->getDisplay());
        self::assertStringContainsString('A random task', $commandTester->getDisplay());
        self::assertStringContainsString('A second random task', $commandTester->getDisplay());
        self::assertStringContainsString('Expression', $commandTester->getDisplay());
        self::assertStringContainsString('* * * * *', $commandTester->getDisplay());
        self::assertStringContainsString('* * * * *', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution date', $commandTester->getDisplay());
        self::assertStringContainsString('Next execution date', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution duration', $commandTester->getDisplay());
        self::assertStringContainsString('Last execution memory usage', $commandTester->getDisplay());
        self::assertStringContainsString('Not tracked', $commandTester->getDisplay());
        self::assertStringContainsString('State', $commandTester->getDisplay());
        self::assertStringContainsString('Tags', $commandTester->getDisplay());
        self::assertStringContainsString('app', $commandTester->getDisplay());
        self::assertStringContainsString('slow', $commandTester->getDisplay());
        self::assertStringContainsString('app', $commandTester->getDisplay());
        self::assertStringContainsString('fast', $commandTester->getDisplay());
    }

    public function testCommandCanReturnTasksWithInvalidExpressionFilter(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturn(new TaskList());
        $taskList->expects(self::once())->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $commandTester = new CommandTester(new ListTasksCommand($scheduler));
        $commandTester->execute([
            '--expression' => '0 * * * *',
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] No tasks found', $commandTester->getDisplay());
    }

    public function testCommandCanReturnTasksWithInvalidStateFilter(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturn(new TaskList());
        $taskList->expects(self::once())->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $commandTester = new CommandTester(new ListTasksCommand($scheduler));
        $commandTester->execute([
            '--state' => 'test',
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] No tasks found', $commandTester->getDisplay());
    }

    public function testCommandCanReturnTasksWithInvalidStateAndExpressionFilter(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturn(new TaskList());
        $taskList->expects(self::once())->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $commandTester = new CommandTester(new ListTasksCommand($scheduler));
        $commandTester->execute([
            '--expression' => '0 * * * *',
            '--state' => 'started',
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('[WARNING] No tasks found', $commandTester->getDisplay());
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideStateOption(): Generator
    {
        yield ['--state'];
        yield ['-s'];
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideOptions(): Generator
    {
        yield ['--expression', '--state'];
    }
}
