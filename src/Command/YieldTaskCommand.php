<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class YieldTaskCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:yield';

    public function __construct(private SchedulerInterface $scheduler)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription(description: 'Yield a task')
            ->setDefinition([
                new InputArgument(name: 'name', mode: InputArgument::REQUIRED, description: 'The task to yield'),
                new InputOption(name: 'async', shortcut: 'a', mode: InputOption::VALUE_NONE, description: 'Yield the task using the message bus'),
                new InputOption(name: 'force', shortcut: 'f', mode: InputOption::VALUE_NONE, description: 'Force the operation without confirmation'),
            ])
            ->setHelp(
                help:
                <<<'EOF'
                    The <info>%command.name%</info> command yield a task.

                        <info>php %command.full_name%</info>

                    Use the name argument to specify the task to yield:
                        <info>php %command.full_name% <name></info>

                    Use the --async option to perform the yield using the message bus:
                        <info>php %command.full_name% <name> --async</info>

                    Use the --force option to force the task yield without asking for confirmation:
                        <info>php %command.full_name% <name> --force</info>
                    EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor(argumentName: 'name')) {
            $storedTasks = $this->scheduler->getTasks();

            $storedTasks->walk(func: static function (TaskInterface $task) use ($suggestions): void {
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

        if (true === $force || $symfonyStyle->confirm(question: 'Do you want to yield this task?', default: false)) {
            try {
                $this->scheduler->yieldTask(name: $name, async: $input->getOption('async'));
            } catch (Throwable $throwable) {
                $symfonyStyle->error(message: [
                    'An error occurred when trying to yield the task:',
                    $throwable->getMessage(),
                ]);

                return self::FAILURE;
            }

            $symfonyStyle->success(message: sprintf('The task "%s" has been yielded', $name));

            return self::SUCCESS;
        }

        $symfonyStyle->warning(message: sprintf('The task "%s" has not been yielded', $name));

        return self::FAILURE;
    }
}
