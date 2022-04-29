<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

use function filter_var;
use function is_string;
use function sprintf;
use const FILTER_SANITIZE_STRING;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsCommand(
    name: 'scheduler:remove:failed',
    description: 'Remove given task from the scheduler',
)]
final class RemoveFailedTaskCommand extends Command
{
    public function __construct(
        private SchedulerInterface $scheduler,
        private WorkerInterface $worker
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument(name: 'name', mode: InputArgument::REQUIRED, description: 'The name of the task to remove'),
                new InputOption(name: 'force', shortcut: 'f', mode: InputOption::VALUE_NONE, description: 'Force the operation without confirmation'),
            ])
            ->setHelp(
                help:
                <<<'EOF'
                    The <info>%command.name%</info> command remove a failed task.

                        <info>php %command.full_name%</info>

                    Use the task-name argument to specify the task to remove:
                        <info>php %command.full_name% <task-name></info>

                    Use the --force option to force the task deletion without asking for confirmation:
                        <info>php %command.full_name% <task-name> --force</info>
                    EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor(argumentName: 'name')) {
            $failedTasks = $this->worker->getFailedTasks();

            $failedTasks->walk(func: static function (TaskInterface $task) use ($suggestions): void {
                $suggestions->suggestValue(value: new Suggestion(value: $task->getName()));
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle(input: $input, output: $output);

        $name = $input->getArgument(name: 'name');
        $force = $input->getOption(name: 'force');

        $name = filter_var(value: $name, filter: FILTER_SANITIZE_STRING);
        if (!is_string($name)) {
            throw new InvalidArgumentException(message: sprintf('The task name "%s" is not valid.', $name));
        }

        try {
            $failedTasks = $this->worker->getFailedTasks();
            $toRemoveTask = $failedTasks->get(taskName: $name);
        } catch (InvalidArgumentException) {
            $symfonyStyle->error(message: sprintf('The task "%s" does not fails', $name));

            return self::FAILURE;
        }

        if (true === $force || $symfonyStyle->confirm(question: 'Do you want to permanently remove this task?', default: false)) {
            try {
                $this->scheduler->unschedule(taskName: $toRemoveTask->getName());
            } catch (Throwable $throwable) {
                $symfonyStyle->error(message: [
                    'An error occurred when trying to unschedule the task:',
                    $throwable->getMessage(),
                ]);

                return self::FAILURE;
            }

            $symfonyStyle->success(message: sprintf('The task "%s" has been unscheduled', $toRemoveTask->getName()));

            return self::SUCCESS;
        }

        $symfonyStyle->note(message: sprintf('The task "%s" has not been unscheduled', $toRemoveTask->getName()));

        return self::FAILURE;
    }
}
