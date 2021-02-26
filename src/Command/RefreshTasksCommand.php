<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RefreshTasksCommand extends Command
{
    private SchedulerInterface $scheduler;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:refresh';

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
            ->setDescription('Refresh the tasks')
            ->setDefinition([
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation without confirmation'),
            ])
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command refresh the tasks.

    <info>php %command.full_name%</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $tasks = $this->scheduler->getTasks();
        $style->info(sprintf('Found %d tasks to update', $tasks->count()));

        if ($input->getOption('force') || $style->confirm('Do you want to refresh the tasks?', false)) {
            try {
                array_walk($tasks, fn (TaskInterface $task): void => $this->scheduler->update($task->getName(), $task));
            } catch (Throwable $throwable) {
                $style->error([
                    'The tasks cannot be refreshed, an error occurred:',
                    $throwable->getMessage(),
                ]);

                return Command::FAILURE;
            }

            $style->success('The tasks have been refreshed');

            return Command::SUCCESS;
        }

        $style->warning('The tasks haven\'t been refreshed');

        return Command::FAILURE;
    }
}
