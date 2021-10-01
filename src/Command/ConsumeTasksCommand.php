<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\WorkerSleepingEvent;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerConfiguration;
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
use function in_array;
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
     * @var string|null
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
        $this->logger = $logger ?? new NullLogger();

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
                new InputOption('force', null, InputOption::VALUE_NONE, 'Force the worker to wait for tasks even if no tasks are currently available'),
                new InputOption('lazy', null, InputOption::VALUE_NONE, 'Force the scheduler to retrieve the tasks using lazy-loading'),
                new InputOption('strict', null, InputOption::VALUE_NONE, 'Force the scheduler to check the date before retrieving the tasks'),
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

                    Use the --force option to force the worker to wait for tasks every minutes even if no tasks are currently available:
                        <info>php %command.full_name% --force</info>

                    Use the --lazy option to force the scheduler to retrieve the tasks using lazy-loading:
                        <info>php %command.full_name% --lazy</info>

                    Use the --strict option to force the scheduler to check the date before retrieving the tasks:
                        <info>php %command.full_name% --strict</info>
                    EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $wait = $input->getOption('wait');
        $force = $input->getOption('force');
        $lazy = $input->getOption('lazy');
        $strict = $input->getOption('strict');

        $dueTasks = $this->scheduler->getDueTasks(true === $lazy, true === $strict)->filter(static fn (TaskInterface $task): bool => !$task instanceof ProbeTask);
        if (0 === $dueTasks->count() && false === $wait) {
            $symfonyStyle->warning('No due tasks found');

            return self::SUCCESS;
        }

        if (false === $force) {
            $nonPausedTasks = $dueTasks->filter(static fn (TaskInterface $task): bool => $task->getState() !== TaskInterface::PAUSED);
            if (0 === $nonPausedTasks->count()) {
                $symfonyStyle->warning([
                    'Each tasks has already been executed for the current minute',
                    sprintf('Consider calling this command again at "%s"', (new DateTimeImmutable('+ 1 minute'))->format('Y-m-d h:i')),
                ]);

                return self::SUCCESS;
            }
        }

        $stopOptions = [];

        if (null !== $limit = $input->getOption('limit')) {
            $stopOptions[] = sprintf('%s task%s %s been consumed', $limit, (int) $limit > 1 ? 's' : '', (int) $limit > 1 ? 'have' : 'has');
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber((int) $limit, $this->logger));
        }

        if (null !== $timeLimit = $input->getOption('time-limit')) {
            $stopOptions[] = sprintf('it has been running for %d seconds', $timeLimit);
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitSubscriber((int) $timeLimit, $this->logger));
        }

        if (null !== $failureLimit = $input->getOption('failure-limit')) {
            $stopOptions[] = sprintf('%d task%s %s failed', $failureLimit, (int) $failureLimit > 1 ? 's' : '', (int) $failureLimit > 1 ? 'have' : 'has');
            $this->eventDispatcher->addSubscriber(new StopWorkerOnFailureLimitSubscriber((int) $failureLimit, $this->logger));
        }

        if ([] !== $stopOptions) {
            $last = array_pop($stopOptions);
            $stopsWhen = ([] !== $stopOptions ? implode(', ', $stopOptions).' or ' : '').$last;
            $symfonyStyle->comment([
                'The worker will automatically exit once:',
                sprintf('- %s', $stopsWhen),
            ]);
        }

        if (true === $wait) {
            $symfonyStyle->note('The worker will wait for tasks every minutes');
        }

        $symfonyStyle->comment('Quit the worker with CONTROL-C.');

        if (OutputInterface::VERBOSITY_VERY_VERBOSE !== $output->getVerbosity()) {
            $symfonyStyle->note(sprintf('The task%s output can be displayed if the -vv option is used', $dueTasks->count() > 1 ? 's' : ''));
        }

        if ($output->isVeryVerbose()) {
            $this->registerOutputSubscriber($symfonyStyle);
        }

        $this->registerWorkerSleepingListener($symfonyStyle);
        $this->registerTaskExecutedSubscriber($symfonyStyle);

        $workerConfiguration = WorkerConfiguration::create();
        $workerConfiguration->mustStrictlyCheckDate(true === $strict);
        $workerConfiguration->mustSleepUntilNextMinute(true === $wait);

        try {
            $this->worker->execute($workerConfiguration);
        } catch (Throwable $throwable) {
            $symfonyStyle->error([
                'An error occurred when executing the tasks',
                $throwable->getMessage(),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function registerOutputSubscriber(SymfonyStyle $symfonyStyle): void
    {
        $this->eventDispatcher->addListener(TaskExecutedEvent::class, static function (TaskExecutedEvent $event) use ($symfonyStyle): void {
            $output = $event->getOutput();
            if (null === $output->getOutput()) {
                return;
            }

            $symfonyStyle->note(sprintf('Output for task "%s":', $event->getTask()->getName()));
            $symfonyStyle->text($output->getOutput());
        });
    }

    private function registerTaskExecutedSubscriber(SymfonyStyle $symfonyStyle): void
    {
        $this->eventDispatcher->addListener(TaskExecutedEvent::class, static function (TaskExecutedEvent $event) use ($symfonyStyle): void {
            $task = $event->getTask();
            $output = $event->getOutput();
            $taskExecutionDuration = Helper::formatTime($task->getExecutionComputationTime() / 1000);
            $taskExecutionMemoryUsage = Helper::formatMemory($task->getExecutionMemoryUsage());

            if (in_array($task->getExecutionState(), [TaskInterface::TO_RETRY, TaskInterface::INCOMPLETE], true)) {
                $symfonyStyle->warning([
                    sprintf('The task "%s" cannot be executed fully', $task->getName()),
                    'The task will be retried next time',
                ]);

                return;
            }

            if (Output::ERROR === $output->getType()) {
                $symfonyStyle->error([
                    sprintf('Task "%s" failed. (Duration: %s, Memory used: %s)', $task->getName(), $taskExecutionDuration, $taskExecutionMemoryUsage),
                ]);

                return;
            }

            $symfonyStyle->success([
                sprintf('Task "%s" succeed. (Duration: %s, Memory used: %s)', $task->getName(), $taskExecutionDuration, $taskExecutionMemoryUsage),
            ]);
        });
    }

    private function registerWorkerSleepingListener(SymfonyStyle $symfonyStyle): void
    {
        $this->eventDispatcher->addListener(WorkerSleepingEvent::class, static function (WorkerSleepingEvent $event) use ($symfonyStyle): void {
            $symfonyStyle->info(sprintf('The worker is currently sleeping during %d seconds', $event->getSleepDuration()));
        });
    }
}
