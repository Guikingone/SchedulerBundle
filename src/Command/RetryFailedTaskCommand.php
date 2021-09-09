<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Worker\WorkerConfiguration;
use Symfony\Component\Console\Command\Command;
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
final class RetryFailedTaskCommand extends Command
{
    private WorkerInterface $worker;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:retry:failed';

    public function __construct(
        WorkerInterface $worker,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger = null
    ) {
        $this->worker = $worker;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?? new NullLogger();

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Retries one or more tasks from the failed tasks')
            ->setDefinition([
                new InputArgument('name', InputArgument::REQUIRED, 'Specific task name(s) to retry'),
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation without confirmation'),
            ])
            ->setHelp(
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $force = $input->getOption('force');

        try {
            $task = $this->worker->getFailedTasks()->get($name);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $symfonyStyle->error(sprintf('The task "%s" does not fails', $name));

            return self::FAILURE;
        }

        if (true === $force || $symfonyStyle->confirm('Do you want to retry this task?', false)) {
            $this->eventDispatcher->dispatch(new StopWorkerOnTaskLimitSubscriber(1, $this->logger));

            try {
                $this->worker->execute(WorkerConfiguration::create(), $task);
            } catch (Throwable $throwable) {
                $symfonyStyle->error([
                    'An error occurred when trying to retry the task:',
                    $throwable->getMessage(),
                ]);

                return self::FAILURE;
            }

            $symfonyStyle->success(sprintf('The task "%s" has been retried', $task->getName()));

            return self::SUCCESS;
        }

        $symfonyStyle->warning(sprintf('The task "%s" has not been retried', $task->getName()));

        return self::FAILURE;
    }
}
