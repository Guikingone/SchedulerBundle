<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use Throwable;
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
    public function run(TaskInterface $task, WorkerInterface $worker): Output
    {
        if (!$task instanceof CommandTask) {
            return new Output($task, null, Output::ERROR);
        }

        $this->application->setCatchExceptions(false);
        $this->application->setAutoExit(false);

        $input = $this->buildInput($task);
        $bufferedOutput = new BufferedOutput();

        try {
            $statusCode = $this->application->run($input, $bufferedOutput);
            if (Command::FAILURE === $statusCode) {
                return new Output($task, $bufferedOutput->fetch(), Output::ERROR);
            }
        } catch (Throwable $throwable) {
            return new Output($task, $bufferedOutput->fetch(), Output::ERROR);
        }

        return new Output($task, $bufferedOutput->fetch());
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof CommandTask;
    }

    private function buildInput(CommandTask $commandTask): StringInput
    {
        $command = $this->application->find($commandTask->getCommand());
        if (null === $name = $command->getName()) {
            throw new InvalidArgumentException('The command name must be set');
        }

        return new StringInput(sprintf('%s %s %s', $name, implode(' ', $commandTask->getArguments()), implode(' ', $this->buildOptions($commandTask))));
    }

    private function buildOptions(CommandTask $commandTask): array
    {
        $options = [];
        foreach ($commandTask->getOptions() as $key => $option) {
            if (is_int($key)) {
                $options[] = 0 === strpos($option, '--') ? $option : sprintf('--%s', $option);

                continue;
            }

            $options[] = sprintf('%s %s', 0 === strpos($key, '--') ? $key : sprintf('--%s', $key), $option);
        }

        return $options;
    }
}
