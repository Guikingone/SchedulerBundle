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

        static::assertSame('scheduler:list', $command->getName());
        static::assertSame('List the tasks', $command->getDescription());
        static::assertTrue($command->getDefinition()->hasOption('expression'));
        static::assertSame('The expression of the tasks', $command->getDefinition()->getOption('expression')->getDescription());
        static::assertNull($command->getDefinition()->getOption('expression')->getShortcut());
        static::assertTrue($command->getDefinition()->hasOption('state'));
        static::assertSame('The state of the tasks', $command->getDefinition()->getOption('state')->getDescription());
        static::assertSame('s', $command->getDefinition()->getOption('state')->getShortcut());
        static::assertSame($command->getHelp(), <<<'EOF'
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

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    /**
     * @dataProvider provideStateOption
     */
    public function testCommandCanListTaskWithSpecificState(string $stateOption): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getDescription')->willReturn('A random task');
        $task->expects(self::exactly(2))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new \DateTimeImmutable());
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
            $stateOption => TaskInterface::ENABLED,
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[OK] 1 task found', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Description', $tester->getDisplay());
        static::assertStringContainsString('Expression', $tester->getDisplay());
        static::assertStringContainsString('Last execution date', $tester->getDisplay());
        static::assertStringContainsString('Next execution date', $tester->getDisplay());
        static::assertStringContainsString('Last execution duration', $tester->getDisplay());
        static::assertStringContainsString('State', $tester->getDisplay());
        static::assertStringContainsString('Tags', $tester->getDisplay());
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
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new \DateTimeImmutable());
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

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[OK] 1 task found', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Description', $tester->getDisplay());
        static::assertStringContainsString('Expression', $tester->getDisplay());
        static::assertStringContainsString('Last execution date', $tester->getDisplay());
        static::assertStringContainsString('Next execution date', $tester->getDisplay());
        static::assertStringContainsString('Last execution duration', $tester->getDisplay());
        static::assertStringContainsString('State', $tester->getDisplay());
        static::assertStringContainsString('Tags', $tester->getDisplay());
    }

    public function testCommandCanReturnTasksWithoutFilter(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getDescription')->willReturn('A random task');
        $task->expects(self::exactly(2))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new \DateTimeImmutable());
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

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[OK] 1 task found', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Description', $tester->getDisplay());
        static::assertStringContainsString('Expression', $tester->getDisplay());
        static::assertStringContainsString('Last execution date', $tester->getDisplay());
        static::assertStringContainsString('Next execution date', $tester->getDisplay());
        static::assertStringContainsString('Last execution duration', $tester->getDisplay());
        static::assertStringContainsString('State', $tester->getDisplay());
        static::assertStringContainsString('Tags', $tester->getDisplay());
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
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new \DateTimeImmutable());
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('getTags')->willReturn(['app', 'slow']);

        $secondTasks = $this->createMock(TaskInterface::class);
        $secondTasks->expects(self::once())->method('getName')->willReturn('bar');
        $secondTasks->expects(self::once())->method('getDescription')->willReturn('A second random task');
        $secondTasks->expects(self::exactly(2))->method('getExpression')->willReturn('* * * * *');
        $secondTasks->expects(self::exactly(2))->method('getLastExecution')->willReturn(new \DateTimeImmutable());
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

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[OK] 2 tasks found', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('foo', $tester->getDisplay());
        static::assertStringContainsString('bar', $tester->getDisplay());
        static::assertStringContainsString('Description', $tester->getDisplay());
        static::assertStringContainsString('A random task', $tester->getDisplay());
        static::assertStringContainsString('A second random task', $tester->getDisplay());
        static::assertStringContainsString('Expression', $tester->getDisplay());
        static::assertStringContainsString('* * * * *', $tester->getDisplay());
        static::assertStringContainsString('* * * * *', $tester->getDisplay());
        static::assertStringContainsString('Last execution date', $tester->getDisplay());
        static::assertStringContainsString('Next execution date', $tester->getDisplay());
        static::assertStringContainsString('Last execution duration', $tester->getDisplay());
        static::assertStringContainsString('Last execution memory usage', $tester->getDisplay());
        static::assertStringContainsString('State', $tester->getDisplay());
        static::assertStringContainsString('Tags', $tester->getDisplay());
        static::assertStringContainsString('app', $tester->getDisplay());
        static::assertStringContainsString('slow', $tester->getDisplay());
        static::assertStringContainsString('app', $tester->getDisplay());
        static::assertStringContainsString('fast', $tester->getDisplay());
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

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
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

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
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

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[WARNING] No tasks found', $tester->getDisplay());
    }

    public function provideStateOption(): \Generator
    {
        yield ['--state'];
        yield ['-s'];
    }

    public function provideExpressionOption(): \Generator
    {
        yield ['--expression'];
    }

    public function provideOptions(): \Generator
    {
        yield ['--expression', '--state'];
    }
}
