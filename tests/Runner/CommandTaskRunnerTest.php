<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SchedulerBundle\Runner\CommandTaskRunner;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use function sprintf;

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

    public function testApplicationIsUsed(): void
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
        self::assertSame(TaskInterface::SUCCEED, $commandTask->getExecutionState());
    }

    public function testCommandCanBeCalledWhenRegistered(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $application = new Application();
        $application->add(new FooCommand());

        $commandTask = new CommandTask('foo', 'app:foo');

        $commandTaskRunner = new CommandTaskRunner($application);
        $output = $commandTaskRunner->run($commandTask, $worker);

        self::assertSame('This command is executed in "" env', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
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
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
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
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
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
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
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
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}

final class FooCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'app:foo';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL)
            ->addOption('wait', 'w', InputOption::VALUE_NONE)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ('' !== $input->getOption('env')) {
            $output->write(sprintf('This command is executed in "%s" env', $input->getOption('env')));
        }

        if (true === $input->getOption('wait')) {
            $output->write('This command will wait');
        }

        return self::SUCCESS;
    }
}

final class BarCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'app:bar';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasArgument('name')) {
            $output->write(sprintf('This command has the "%s" name', $input->getArgument('name')));

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }
}
