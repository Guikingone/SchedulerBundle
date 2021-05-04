<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RemoveFailedTaskCommand extends Command
{
    private SchedulerInterface $scheduler;
    private WorkerInterface $worker;

    /**
     * @var string
     */
    protected static $defaultName = 'scheduler:remove:failed';

    public function __construct(SchedulerInterface $scheduler, WorkerInterface $worker)
    {
        $this->scheduler = $scheduler;
        $this->worker = $worker;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Remove given task from the scheduler')
            ->setDefinition([
                new InputArgument('name', InputArgument::REQUIRED, 'The name of the task to remove'),
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation without confirmation'),
            ])
            ->setHelp(
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');

        $toRemoveTask = $this->worker->getFailedTasks()->get($name);
        if (!$toRemoveTask instanceof TaskInterface) {
            $message = sprintf('The task "%s" does not fails', $name);
            $symfonyStyle->error($message);

            return self::FAILURE;
        }

        if ($input->getOption('force') || $symfonyStyle->confirm('Do you want to permanently remove this task?', false)) {
            try {
                $this->scheduler->unschedule($toRemoveTask->getName());
            } catch (Throwable $throwable) {
                $symfonyStyle->error([
                    'An error occurred when trying to unschedule the task:',
                    $throwable->getMessage(),
                ]);

                return self::FAILURE;
            }
            $message = sprintf('The task "%s" has been unscheduled', $toRemoveTask->getName());

            $symfonyStyle->success($message);

            return self::SUCCESS;
        }
        $message = sprintf('The task "%s" has not been unscheduled', $toRemoveTask->getName());

        $symfonyStyle->note($message);

        return self::FAILURE;
    }
}
