<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Cron\CronExpression;
use SchedulerBundle\Task\ChainedTask;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use ReflectionClass;
use Throwable;

use function array_unique;
use function array_walk;
use function implode;
use function sprintf;

use const DATE_ATOM;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsCommand(
    name: 'scheduler:list',
    description: 'List the tasks',
)]
final class ListTasksCommand extends Command
{
    /**
     * {@inheritdoc}
     */
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
            ->setDefinition([
                new InputOption(name: 'expression', shortcut: null, mode: InputOption::VALUE_OPTIONAL, description: 'The expression of the tasks'),
                new InputOption(name: 'state', shortcut: 's', mode: InputOption::VALUE_OPTIONAL, description: 'The state of the tasks'),
            ])
            ->setHelp(
                help:
                <<<'EOF'
                    The <info>%command.name%</info> command list tasks.

                        <info>php %command.full_name%</info>

                    Use the --expression option to list the tasks with a specific expression:
                        <info>php %command.full_name% --expression=* * * * *</info>

                    Use the --state option to list the tasks with a specific state:
                        <info>php %command.full_name% --state=paused</info>

                    Use the -s option to list the tasks with a specific state:
                        <info>php %command.full_name% -s=paused</info>
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
        $storedTasks = $this->scheduler->getTasks();

        if ($input->mustSuggestOptionValuesFor(optionName: 'expression')) {
            $expressionList = array_unique(array: $storedTasks->map(func: static fn (TaskInterface $task): string => $task->getExpression()));

            array_walk(array: $expressionList, callback: static function (string $expression) use ($suggestions): void {
                $suggestions->suggestValue(value: new Suggestion(value: $expression));
            });
        }

        if ($input->mustSuggestOptionValuesFor(optionName: 'state')) {
            $stateList = array_unique(array: $storedTasks->map(func: static fn (TaskInterface $task): string => $task->getState()));

            array_walk(array: $stateList, callback: static function (string $state) use ($suggestions): void {
                $suggestions->suggestValue(value: new Suggestion(value: $state));
            });
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle(input: $input, output: $output);

        $tasks = $this->scheduler->getTasks();
        if (0 === $tasks->count()) {
            $symfonyStyle->warning(message: 'No tasks found');

            return self::SUCCESS;
        }

        if (null !== $state = $input->getOption(name: 'state')) {
            $tasks = $tasks->filter(filter: static fn (TaskInterface $task): bool => $state === $task->getState());
        }

        if (null !== $expression = $input->getOption(name: 'expression')) {
            $tasks = $tasks->filter(filter: static fn (TaskInterface $task): bool => $expression === $task->getExpression());
        }

        if (0 === $tasks->count()) {
            $symfonyStyle->warning(message: 'No tasks found');

            return self::SUCCESS;
        }

        $symfonyStyle->success(message: sprintf('%d task%s found', $tasks->count(), $tasks->count() > 1 ? 's' : ''));
        $table = new Table(output: $output);
        $table->setHeaders(headers: ['Type', 'Name', 'Description', 'Expression',  'Last execution date', 'Next execution date', 'Last execution duration', 'Last execution memory usage', 'State', 'Tags']);

        $tasks->walk(func: static function (TaskInterface $task) use ($table): void {
            $lastExecutionDate = $task->getLastExecution();

            $table->addRow(row: [
                (new ReflectionClass(objectOrClass: $task))->getShortName(),
                $task->getName(),
                $task->getDescription() ?? 'No description set',
                $task->getExpression(),
                null !== $lastExecutionDate ? $lastExecutionDate->format(format: DATE_ATOM) : 'Not executed',
                (new CronExpression(expression: $task->getExpression()))->getNextRunDate()->format(format: DATE_ATOM),
                null !== $task->getExecutionComputationTime() ? Helper::formatTime(secs: $task->getExecutionComputationTime() / 1000) : 'Not tracked',
                0 !== $task->getExecutionMemoryUsage() ? Helper::formatMemory(memory: $task->getExecutionMemoryUsage()) : 'Not tracked',
                $task->getState(),
                implode(separator: ', ', array: $task->getTags()),
            ]);

            if ($task instanceof ChainedTask) {
                $subTasks = $task->getTasks();

                $subTasks->walk(func: static function (TaskInterface $subTask) use ($table): void {
                    $table->addRow(row: [
                        '<info>          ></info>',
                        $subTask->getName(),
                        $subTask->getDescription() ?? 'No description set',
                        '-',
                        null !== $subTask->getLastExecution() ? $subTask->getLastExecution()->format(format: DATE_ATOM) : 'Not executed',
                        (new CronExpression(expression: $subTask->getExpression()))->getNextRunDate()->format(format: DATE_ATOM),
                        null !== $subTask->getExecutionComputationTime() ? Helper::formatTime(secs: $subTask->getExecutionComputationTime() / 1000) : 'Not tracked',
                        0 !== $subTask->getExecutionMemoryUsage() ? Helper::formatMemory(memory: $subTask->getExecutionMemoryUsage()) : 'Not tracked',
                        $subTask->getState(),
                        implode(separator: ', ', array: $subTask->getTags()),
                    ]);
                });
            }
        });


        $table->render();

        return self::SUCCESS;
    }
}
