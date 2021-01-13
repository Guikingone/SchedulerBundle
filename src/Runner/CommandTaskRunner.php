<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Runner;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use SchedulerBundle\Exception\UnrecognizedCommandException;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class CommandTaskRunner implements RunnerInterface
{
    private $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        $input = $this->buildInput($task);
        $output = new BufferedOutput();

        $this->application->setCatchExceptions(false);
        $this->application->setAutoExit(false);

        $task->setExecutionState(TaskInterface::RUNNING);

        try {
            $statusCode = $this->application->run($input, $output);
            if (Command::FAILURE === $statusCode) {
                return new Output($task, $output->fetch(), Output::ERROR);
            }
        } catch (\Throwable $throwable) {
            $task->setExecutionState(TaskInterface::ERRORED);

            return new Output($task, $output->fetch(), Output::ERROR);
        }

        $task->setExecutionState(TaskInterface::SUCCEED);

        return new Output($task, $output->fetch());
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof CommandTask;
    }

    private function buildInput(TaskInterface $task): InputInterface
    {
        $command = $this->findCommand($task->getCommand());
        $options = $this->buildOptions($task);

        return new StringInput(sprintf('%s %s %s', $command->getName(), implode(' ', $task->getArguments()), implode(' ', $options)));
    }

    private function buildOptions(TaskInterface $task): array
    {
        $arguments = [];
        foreach ($task->getOptions() as $key => $argument) {
            $arguments[] = sprintf('%s="%s"', $key, $argument);
        }

        return $arguments;
    }

    private function findCommand(string $command): Command
    {
        $registeredCommands = $this->application->all();
        if (\array_key_exists($command, $registeredCommands)) {
            return $registeredCommands[$command];
        }

        foreach ($registeredCommands as $registeredCommand) {
            if ($command === \get_class($registeredCommand)) {
                return $registeredCommand;
            }
        }

        throw new UnrecognizedCommandException(sprintf('The given command "%s" cannot be found!', $command));
    }
}
