<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Application;
use SchedulerBundle\Runner\CommandTaskRunner;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\Output;
use Symfony\Component\Console\Command\Command;
use Tests\SchedulerBundle\Runner\Assets\BarCommand;
use Tests\SchedulerBundle\Runner\Assets\FooCommand;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTaskRunnerTest extends TestCase
{
    public function testRunnerSupport(): void
    {
        $application = new Application();

        $commandTaskRunner = new CommandTaskRunner($application);

        self::assertFalse($commandTaskRunner->support(new NullTask('foo')));
        self::assertTrue($commandTaskRunner->support(new CommandTask('foo', 'app:foo')));
    }

    public function testRunnerCannotRunInvalidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $application = new Application();

        $commandTaskRunner = new CommandTaskRunner($application);
        $output = $commandTaskRunner->run(new ShellTask('foo', []), $worker);

        self::assertSame(Output::ERROR, $output->getType());
        self::assertNull($output->getOutput());
        self::assertInstanceOf(ShellTask::class, $output->getTask());
    }

    public function testApplicationCanReturnValidCode(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $commandTask = new CommandTask('foo', 'app:foo');

        $application = $this->createMock(Application::class);
        $application->expects(self::once())->method('setCatchExceptions')->with(self::equalTo(false));
        $application->expects(self::once())->method('setAutoExit')->with(self::equalTo(false));
        $application->expects(self::once())->method('find')->willReturn(new FooCommand());
        $application->expects(self::once())->method('run')->willReturn(0);

        $commandTaskRunner = new CommandTaskRunner($application);

        $output = $commandTaskRunner->run($commandTask, $worker);

        self::assertSame($commandTask, $output->getTask());
        self::assertNull($commandTask->getExecutionState());
        self::assertSame(Output::SUCCESS, $output->getType());
    }

    public function testApplicationCanReturnInvalidCode(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $commandTask = new CommandTask('foo', 'app:foo');

        $application = $this->createMock(Application::class);
        $application->expects(self::once())->method('setCatchExceptions')->with(self::equalTo(false));
        $application->expects(self::once())->method('setAutoExit')->with(self::equalTo(false));
        $application->expects(self::once())->method('find')->willReturn(new FooCommand());
        $application->expects(self::once())->method('run')->willReturn(Command::FAILURE);

        $commandTaskRunner = new CommandTaskRunner($application);

        $output = $commandTaskRunner->run($commandTask, $worker);

        self::assertSame($commandTask, $output->getTask());
        self::assertNull($commandTask->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
    }

    public function testCommandCanBeCalledWhenRegistered(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $application = new Application();
        $application->add(new FooCommand());

        $commandTask = new CommandTask('foo', 'app:foo', [], [
            '--env' => 'foo',
        ]);

        $commandTaskRunner = new CommandTaskRunner($application);
        $output = $commandTaskRunner->run($commandTask, $worker);

        self::assertSame('This command is executed in "foo" env', $output->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testCommandCanBeCalledWithOptions(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $application = new Application();
        $application->add(new FooCommand());

        $commandTask = new CommandTask('foo', 'app:foo', [], ['--env' => 'test']);

        $commandTaskRunner = new CommandTaskRunner($application);
        $output = $commandTaskRunner->run($commandTask, $worker);

        self::assertNotNull($output->getOutput());
        self::assertStringContainsString('This command is executed in "test" env', $output->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testCommandCanBeCalledWithEmptyOptions(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $application = new Application();
        $application->add(new FooCommand());

        $commandTask = new CommandTask('foo', 'app:foo', [], ['--wait']);

        $commandTaskRunner = new CommandTaskRunner($application);
        $output = $commandTaskRunner->run($commandTask, $worker);

        self::assertNotNull($output->getOutput());
        self::assertStringContainsString('This command will wait', $output->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testCommandCanBeCalledWithOptionsButWithoutDashes(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $application = new Application();
        $application->add(new BarCommand());

        $commandTask = new CommandTask('foo', 'app:bar', ['name' => 'bar'], ['env' => 'test']);

        $commandTaskRunner = new CommandTaskRunner($application);
        $output = $commandTaskRunner->run($commandTask, $worker);

        self::assertNotNull($output->getOutput());
        self::assertStringContainsString('This command has the "bar" name', $output->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testCommandCanBeCalledWithArgument(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $application = new Application();
        $application->add(new BarCommand());

        $commandTask = new CommandTask('foo', 'app:bar', ['name' => 'bar'], ['--env' => 'test']);

        $commandTaskRunner = new CommandTaskRunner($application);
        $output = $commandTaskRunner->run($commandTask, $worker);

        self::assertNotNull($output->getOutput());
        self::assertStringContainsString('This command has the "bar" name', $output->getOutput());
        self::assertNull($output->getTask()->getExecutionState());
    }
}
