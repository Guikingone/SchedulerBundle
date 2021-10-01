<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use function array_walk;
use function count;
use function implode;
use function in_array;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecuteTaskCommand extends Command
{
    private EventDispatcherInterface $eventDispatcher;
    private SchedulerInterface $scheduler;
    private WorkerInterface $worker;
    private LoggerInterface $logger;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:execute';

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        SchedulerInterface $scheduler,
        WorkerInterface $worker,
        ?LoggerInterface $logger = null
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->scheduler = $scheduler;
        $this->worker = $worker;
        $this->logger = $logger ?? new NullLogger();

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Execute tasks (due or not) depending on filters')
            ->setDefinition([
                new InputOption('due', 'd', InputOption::VALUE_NONE, 'Define if the filters must be applied on due tasks'),
                new InputOption('name', null, InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'The name of the task(s) to execute'),
                new InputOption('expression', null, InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'The expression of the task(s) to execute'),
                new InputOption('tags', 't', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'The tags of the task(s) to execute'),
            ])
            ->setHelp(
                <<<'EOF'
                    The <info>%command.name%</info> command execute tasks.

                        <info>php %command.full_name%</info>

                    Use the --due option to execute the due tasks:
                        <info>php %command.full_name% --due</info>

                    Use the --name option to filter the executed tasks depending on their name:
                        <info>php %command.full_name% --name=foo, bar</info>

                    Use the --expression option to filter the executed tasks depending on their expression:
                        <info>php %command.full_name% --expression=* * * * *</info>

                    Use the --tags option to filter the executed tasks depending on their tags:
                        <info>php %command.full_name% --tags=foo, bar</info>
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

        $dueTasks = $input->getOption('due');

        $tasks = true === $dueTasks ? $this->scheduler->getDueTasks() : $this->scheduler->getTasks();
        if (0 === count($tasks)) {
            $style->warning('No tasks to execute found');

            return self::SUCCESS;
        }

        $style->info(sprintf('Found %d task%s', count($tasks), 1 !== count($tasks) ? 's' : ''));

        $executionOptions = [];

        if ([] !== $names = $input->getOption('name')) {
            $executionOptions[] = sprintf('- Task(s) with the following name(s): %s', implode(', ', $names));
            $tasks = $tasks->filter(static fn (TaskInterface $task): bool => in_array($task->getName(), $names, true));
        }

        if ([] !== $expressions = $input->getOption('expression')) {
            $executionOptions[] = sprintf('- Task(s) with the following expression(s): %s', implode(', ', $expressions));
            $tasks = $tasks->filter(static fn (TaskInterface $task): bool => in_array($task->getExpression(), $expressions, true));
        }

        if ([] !== $tags = $input->getOption('tags')) {
            $executionOptions[] = sprintf('- Task(s) with the following tags(s): %s', implode(', ', $tags));
            $tasks = $tasks->filter(static fn (TaskInterface $task): bool => array_walk($tags, static fn (string $tag): bool => in_array($tag, $task->getTags(), true)));
        }

        $style->info([
            'The tasks following the listed conditions will be executed:',
            implode(PHP_EOL, $executionOptions),
            sprintf('%d task%s to be executed', count($tasks), 1 < count($tasks) ? 's' : ''),
        ]);

        $this->eventDispatcher->dispatch(new StopWorkerOnTaskLimitSubscriber(count($tasks), $this->logger));

        try {
            $this->worker->execute(WorkerConfiguration::create(), ...$tasks->toArray(false));
        } catch (Throwable $throwable) {
            $style->error([
                'An error occurred during the tasks execution:',
                $throwable->getMessage(),
            ]);

            return self::FAILURE;
        }

        $style->success([
            sprintf('%d task%s %s been executed', count($tasks), 1 < count($tasks) ? 's' : '', 1 < count($tasks) ? 'have' : 'has'),
        ]);

        return self::SUCCESS;
    }
}
