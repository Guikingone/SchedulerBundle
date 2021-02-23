<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SchedulerBundle\Exception\UnrecognizedCommandException;
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

        $runner = new CommandTaskRunner($application);

        self::assertFalse($runner->support(new NullTask('foo')));
        self::assertTrue($runner->support(new CommandTask('foo', 'app:foo')));
    }

    public function testApplicationIsUsed(): void
    {
        $task = new CommandTask('foo', 'app:foo');

        $application = $this->createMock(Application::class);
        $application->expects(self::once())->method('setCatchExceptions')->with(self::equalTo(false));
        $application->expects(self::once())->method('setAutoExit')->with(self::equalTo(false));
        $application->expects(self::once())->method('all')->willReturn([
            'app:foo' => new FooCommand(),
        ]);
        $application->expects(self::once())->method('run')->willReturn(0);

        $runner = new CommandTaskRunner($application);

        $output = $runner->run($task);

        self::assertSame($task, $output->getTask());
        self::assertSame(TaskInterface::SUCCEED, $task->getExecutionState());
    }

    public function testCommandCannotBeCalledWithoutBeingRegistered(): void
    {
        $application = new Application();
        $task = new CommandTask('foo', 'app:foo');

        $runner = new CommandTaskRunner($application);
        self::assertTrue($runner->support($task));
        self::expectException(UnrecognizedCommandException::class);
        self::expectExceptionMessage('The given command "app:foo" cannot be found!');
        self::expectExceptionCode(0);
        self::assertNotNull($runner->run($task)->getOutput());
        self::assertSame(Output::ERROR, $runner->run($task)->getOutput());

        $task = new CommandTask('foo', FooCommand::class);
        self::expectException(UnrecognizedCommandException::class);
        self::expectExceptionMessage('The given command "app:foo" cannot be found!');
        self::expectExceptionCode(0);
        self::assertNotNull($runner->run($task)->getOutput());
        self::assertSame(Output::ERROR, $runner->run($task)->getOutput());
    }

    public function testCommandCanBeCalledWhenRegistered(): void
    {
        $application = new Application();
        $application->add(new FooCommand());

        $task = new CommandTask('foo', 'app:foo');

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        self::assertSame('This command is executed in "" env', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testCommandCanBeCalledWithOptions(): void
    {
        $application = new Application();
        $application->add(new FooCommand());

        $task = new CommandTask('foo', 'app:foo', [], ['--env' => 'test']);

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        self::assertStringContainsString('This command is executed in "test" env', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testCommandCanBeCalledWithEmptyOptions(): void
    {
        $application = new Application();
        $application->add(new FooCommand());

        $task = new CommandTask('foo', 'app:foo', [], ['--wait']);

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        self::assertStringContainsString('This command will wait', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testCommandCanBeCalledWithOptionsButWithoutDashes(): void
    {
        $application = new Application();
        $application->add(new BarCommand());

        $task = new CommandTask('foo', 'app:bar', ['name' => 'bar'], ['env' => 'test']);

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        self::assertStringContainsString('This command has the "bar" name', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testCommandCanBeCalledWithArgument(): void
    {
        $application = new Application();
        $application->add(new BarCommand());

        $task = new CommandTask('foo', 'app:bar', ['name' => 'bar'], ['--env' => 'test']);

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        self::assertStringContainsString('This command has the "bar" name', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}

final class FooCommand extends Command
{
    /**
     * @var string
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
     * @var string
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
