<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerConfiguration;
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
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsCommand(
    name: 'scheduler:retry:failed',
    description: 'Retries one or more tasks from the failed tasks',
)]
final class RetryFailedTaskCommand extends Command
{
    private LoggerInterface $logger;

    public function __construct(
        private WorkerInterface $worker,
        private EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument(name: 'name', mode: InputArgument::REQUIRED, description: 'Specific task name(s) to retry'),
                new InputOption(name: 'force', shortcut: 'f', mode: InputOption::VALUE_NONE, description: 'Force the operation without confirmation'),
            ])
            ->setHelp(
                help:
                <<<'EOF'
                    The <info>%command.name%</info> command retry a failed task.

                        <info>php %command.full_name%</info>

                    Use the task-name argument to specify the task to retry:
                        <info>php %command.full_name% <task-name></info>

                    Use the --force option to force the task retry without asking for confirmation:
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

        try {
            $task = $this->worker->getFailedTasks()->get(taskName: $name);
        } catch (InvalidArgumentException) {
            $symfonyStyle->error(message: sprintf('The task "%s" does not fails', $name));

            return self::FAILURE;
        }

        if (true === $force || $symfonyStyle->confirm(question: 'Do you want to retry this task?', default: false)) {
            $this->eventDispatcher->dispatch(event: new StopWorkerOnTaskLimitSubscriber(maximumTasks: 1, logger: $this->logger));

            try {
                $this->worker->execute(configuration: WorkerConfiguration::create(), tasks: $task);
            } catch (Throwable $throwable) {
                $symfonyStyle->error(message: [
                    'An error occurred when trying to retry the task:',
                    $throwable->getMessage(),
                ]);

                return self::FAILURE;
            }

            $symfonyStyle->success(message: sprintf('The task "%s" has been retried', $task->getName()));

            return self::SUCCESS;
        }

        $symfonyStyle->warning(message: sprintf('The task "%s" has not been retried', $task->getName()));

        return self::FAILURE;
    }
}
