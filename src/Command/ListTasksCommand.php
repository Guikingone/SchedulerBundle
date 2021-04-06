<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Cron\CronExpression;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use function array_map;
use function count;
use function implode;
use function sprintf;
use const DATE_ATOM;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ListTasksCommand extends Command
{
    private SchedulerInterface $scheduler;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:list';

    /**
     * {@inheritdoc}
     */
    public function __construct(SchedulerInterface $scheduler)
    {
        $this->scheduler = $scheduler;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('List the tasks')
            ->setDefinition([
                new InputOption('expression', null, InputOption::VALUE_OPTIONAL, 'The expression of the tasks'),
                new InputOption('state', 's', InputOption::VALUE_OPTIONAL, 'The state of the tasks'),
            ])
            ->setHelp(
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
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $tasks = $this->scheduler->getTasks();
        if (0 === count($tasks->toArray())) {
            $symfonyStyle->warning('No tasks found');

            return self::SUCCESS;
        }

        if (null !== $state = $input->getOption('state')) {
            $tasks = $tasks->filter(fn (TaskInterface $task): bool => $state === $task->getState());
        }

        if (null !== $expression = $input->getOption('expression')) {
            $tasks = $tasks->filter(fn (TaskInterface $task): bool => $expression === $task->getExpression());
        }

        $tasks = $tasks->toArray();
        if (0 === count($tasks)) {
            $symfonyStyle->warning('No tasks found');

            return self::SUCCESS;
        }

        $symfonyStyle->success(sprintf('%d task%s found', count($tasks), count($tasks) > 1 ? 's' : ''));

        $table = new Table($output);
        $table->setHeaders(['Name', 'Description', 'Expression', 'Last execution date', 'Next execution date', 'Last execution duration', 'Last execution memory usage', 'State', 'Tags']);
        $table->addRows(array_map(fn (TaskInterface $task): array => [
            $task->getName(),
            $task->getDescription() ?? 'No description set',
            $task->getExpression(),
            null !== $task->getLastExecution() ? $task->getLastExecution()->format(DATE_ATOM) : 'Not executed',
            (new CronExpression($task->getExpression()))->getNextRunDate()->format(DATE_ATOM),
            null !== $task->getExecutionComputationTime() ? Helper::formatTime($task->getExecutionComputationTime() / 1000) : 'Not tracked',
            null !== $task->getExecutionMemoryUsage() ? Helper::formatMemory($task->getExecutionMemoryUsage()) : 'Not tracked',
            $task->getState(),
            implode(', ', $task->getTags()) ?? 'No tags set',
        ], $tasks));

        $table->render();

        return self::SUCCESS;
    }
}
