<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use Symfony\Component\Process\Process;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use function trim;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTaskRunner implements RunnerInterface
{
    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        if (!$task instanceof ShellTask) {
            $task->setExecutionState(TaskInterface::ERRORED);

            return new Output($task, null, Output::ERROR);
        }

        $process = new Process(
            $task->getCommand(),
            $task->getCwd(),
            $task->getEnvironmentVariables(),
            null,
            $task->getTimeout()
        );

        if ($task->mustRunInBackground()) {
            $process->run(null, $task->getEnvironmentVariables());

            $task->setExecutionState(TaskInterface::INCOMPLETE);

            return new Output($task, 'Task is running in background, output is not available');
        }

        $exitCode = $process->run(null, $task->getEnvironmentVariables());

        $output = $task->isOutput() ? trim($process->getOutput()) : null;
        if (0 !== $exitCode) {
            $task->setExecutionState(TaskInterface::ERRORED);

            return new Output($task, $process->getErrorOutput(), Output::ERROR);
        }

        $task->setExecutionState(TaskInterface::SUCCEED);

        return new Output($task, $output);
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof ShellTask;
    }
}
