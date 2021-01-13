<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTaskRunnerTest extends TestCase
{
    public function testRunnerSupport(): void
    {
        $application = new Application();

        $runner = new CommandTaskRunner($application);

        static::assertFalse($runner->support(new NullTask('foo')));
        static::assertTrue($runner->support(new CommandTask('foo', 'app:foo')));
    }

    public function testCommandCannotBeCalledWithoutBeingRegistered(): void
    {
        $application = new Application();
        $task = new CommandTask('foo', 'app:foo');

        $runner = new CommandTaskRunner($application);
        static::assertTrue($runner->support($task));
        static::expectException(UnrecognizedCommandException::class);
        static::expectExceptionMessage('The given command "app:foo" cannot be found!');
        static::assertInstanceOf(Output::class, $runner->run($task)->getOutput());

        $task = new CommandTask('foo', FooCommand::class);
        static::expectException(UnrecognizedCommandException::class);
        static::expectExceptionMessage('The given command "app:foo" cannot be found!');
        static::assertInstanceOf(Output::class, $runner->run($task)->getOutput());
    }

    public function testCommandCanBeCalledWhenRegistered(): void
    {
        $application = new Application();
        $application->add(new FooCommand());

        $task = new CommandTask('foo', 'app:foo');

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        static::assertSame('This command is executed in "" env', $output->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testCommandCanBeCalledWithOptions(): void
    {
        $application = new Application();
        $application->add(new FooCommand());

        $task = new CommandTask('foo', 'app:foo', [], ['--env' => 'test']);

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        static::assertStringContainsString('This command is executed in "test" env', $output->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testCommandCanBeCalledWithArgument(): void
    {
        $application = new Application();
        $application->add(new BarCommand());

        $task = new CommandTask('foo', 'app:bar', ['name' => 'bar'], ['--env' => 'test']);

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        static::assertStringContainsString('This command has the "bar" name', $output->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}

final class FooCommand extends Command
{
    protected static $defaultName = 'app:foo';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasOption('env')) {
            $output->write(sprintf('This command is executed in "%s" env', $input->getOption('env')));

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }
}

final class BarCommand extends Command
{
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
