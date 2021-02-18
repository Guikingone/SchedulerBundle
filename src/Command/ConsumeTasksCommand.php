<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\EventListener\StopWorkerOnFailureLimitSubscriber;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\EventListener\StopWorkerOnTimeLimitSubscriber;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function array_pop;
use function implode;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConsumeTasksCommand extends Command
{
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private SchedulerInterface $scheduler;
    private WorkerInterface $worker;

    /**
     * @var string|mixed
     */
    protected static $defaultName = 'scheduler:consume';

    public function __construct(
        SchedulerInterface $scheduler,
        WorkerInterface $worker,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger = null
    ) {
        $this->scheduler = $scheduler;
        $this->worker = $worker;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?: new NullLogger();

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Consumes due tasks')
            ->setDefinition([
                new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the number of tasks consumed'),
                new InputOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Limit the time in seconds the worker can run'),
                new InputOption('failure-limit', 'f', InputOption::VALUE_REQUIRED, 'Limit the amount of task allowed to fail'),
                new InputOption('wait', 'w', InputOption::VALUE_NONE, 'Set the worker to wait for tasks every minutes'),
            ])
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command consumes due tasks.

    <info>php %command.full_name%</info>

Use the --limit option to limit the number of tasks consumed:
    <info>php %command.full_name% --limit=10</info>

Use the --time-limit option to stop the worker when the given time limit (in seconds) is reached:
    <info>php %command.full_name% --time-limit=3600</info>

Use the --failure-limit option to stop the worker when the given amount of failed tasks is reached:
    <info>php %command.full_name% --failure-limit=5</info>

Use the --wait option to set the worker to wait for tasks every minutes:
    <info>php %command.full_name% --wait</info>
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

        $tasks = $this->scheduler->getDueTasks();
        if (0 === $tasks->count() && !$input->getOption('wait')) {
            $style->warning('No due tasks found');

            return self::SUCCESS;
        }

        $stopOptions = [];

        if (null !== $limit = $input->getOption('limit')) {
            $stopOptions[] = sprintf('%s tasks has been consumed', $limit);
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber((int) $limit, $this->logger));
        }

        if (null !== $timeLimit = $input->getOption('time-limit')) {
            $stopOptions[] = sprintf('it has been running for %d seconds', $timeLimit);
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitSubscriber((int) $timeLimit, $this->logger));
        }

        if (null !== $failureLimit = $input->getOption('failure-limit')) {
            $stopOptions[] = sprintf('%d task%s have failed', $failureLimit, (int) $failureLimit > 1 ? 's' : '');
            $this->eventDispatcher->addSubscriber(new StopWorkerOnFailureLimitSubscriber((int) $failureLimit, $this->logger));
        }

        if ($stopOptions) {
            $last = array_pop($stopOptions);
            $stopsWhen = ($stopOptions ? implode(', ', $stopOptions).' or ' : '').$last;
            $style->comment(sprintf('The worker will automatically exit once %s.', $stopsWhen));
        }

        if ($input->getOption('wait')) {
            $style->note('The worker will wait for tasks every minutes');
        }

        $tasksCount = $tasks->count();

        $style->comment('Quit the worker with CONTROL-C.');

        if (OutputInterface::VERBOSITY_VERBOSE > $output->getVerbosity()) {
            $style->note(sprintf('The task%s output can be displayed if the -vv option is used', $tasksCount > 1 ? 's' : ''));
        }

        if ($output->isVeryVerbose()) {
            $this->registerOutputSubscriber($style);
        }

        $this->registerTaskExecutedSubscriber($style);

        try {
            $this->worker->execute([
                'sleepUntilNextMinute' => $input->getOption('wait'),
            ]);
        } catch (Throwable $throwable) {
            $style->error([
                'An error occurred when executing the tasks',
                $throwable->getMessage(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function registerOutputSubscriber(SymfonyStyle $style): void
    {
        $this->eventDispatcher->addListener(TaskExecutedEvent::class, function (TaskExecutedEvent $event) use ($style): void {
            $output = $event->getOutput();
            if (null === $output->getOutput()) {
                return;
            }

            $style->note(sprintf('Output for task "%s":', $event->getTask()->getName()));
            $style->text($output->getOutput());
        });
    }

    private function registerTaskExecutedSubscriber(SymfonyStyle $style): void
    {
        $this->eventDispatcher->addListener(TaskExecutedEvent::class, function (TaskExecutedEvent $event) use ($style): void {
            $task = $event->getTask();
            $outputType = $event->getOutput()->getType();
            $taskExecutionDuration = Helper::formatTime($task->getExecutionComputationTime() / 1000);
            $taskExecutionMemoryUsage = Helper::formatMemory($task->getExecutionMemoryUsage());

            if (Output::ERROR === $outputType) {
                $style->error([
                    sprintf('Task "%s" failed. (Duration: %s, Memory used: %s)', $task->getName(), $taskExecutionDuration, $taskExecutionMemoryUsage),
                ]);

                return;
            }

            $style->success([
                sprintf('Task "%s" succeed. (Duration: %s, Memory used: %s)', $task->getName(), $taskExecutionDuration, $taskExecutionMemoryUsage),
            ]);
        });
    }
}
