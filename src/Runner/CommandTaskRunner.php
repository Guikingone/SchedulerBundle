<?php

declare(strict_types=1);

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
use Throwable;
use function array_key_exists;
use function get_class;
use function implode;
use function is_int;
use function sprintf;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTaskRunner implements RunnerInterface
{
    private Application $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        if (!$task instanceof CommandTask) {
            return new Output($task, null, Output::ERROR);
        }

        $this->application->setCatchExceptions(false);
        $this->application->setAutoExit(false);

        $input = $this->buildInput($task);
        $output = new BufferedOutput();

        $task->setExecutionState(TaskInterface::RUNNING);

        try {
            $statusCode = $this->application->run($input, $output);
            if (Command::FAILURE === $statusCode) {
                return new Output($task, $output->fetch(), Output::ERROR);
            }
        } catch (Throwable $throwable) {
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

    private function buildInput(CommandTask $task): InputInterface
    {
        $command = $this->findCommand($task->getCommand());
        $options = $this->buildOptions($task);

        return new StringInput(sprintf('%s %s %s', $command->getName(), implode(' ', $task->getArguments()), implode(' ', $options)));
    }

    /**
     * @param CommandTask $task
     *
     * @return array
     */
    private function buildOptions(CommandTask $task): array
    {
        $options = [];
        foreach ($task->getOptions() as $key => $option) {
            if (is_int($key)) {
                $options[] = 0 === strpos($option, '--') ? $option : sprintf('--%s', $option);

                continue;
            }

            $options[] = sprintf('%s %s', 0 === strpos($key, '--') ? $key : sprintf('--%s', $key), $option);
        }

        return $options;
    }

    private function findCommand(string $command): Command
    {
        $registeredCommands = $this->application->all();
        if (array_key_exists($command, $registeredCommands)) {
            return $registeredCommands[$command];
        }

        foreach ($registeredCommands as $registeredCommand) {
            if ($command === get_class($registeredCommand)) {
                return $registeredCommand;
            }
        }

        throw new UnrecognizedCommandException(sprintf('The given command "%s" cannot be found!', $command));
    }
}
