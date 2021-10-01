<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Worker\WorkerConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\Expression\Expression;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use function array_map;
use function get_class;
use function implode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RebootSchedulerCommand extends Command
{
    private SchedulerInterface $scheduler;
    private WorkerInterface $worker;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:reboot';

    public function __construct(
        SchedulerInterface $scheduler,
        WorkerInterface $worker,
        EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null
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
            ->setDefinition([
                new InputOption('dry-run', 'd', InputOption::VALUE_NONE, 'Test the reboot without executing the tasks, the "ready to reboot" tasks are displayed'),
            ])
            ->setDescription('Reboot the scheduler')
            ->setHelp(
                <<<'EOF'
                    The <info>%command.name%</info> command reboot the scheduler.

                        <info>php %command.full_name%</info>

                    Use the --dry-run option to list the tasks executed when the scheduler reboot:
                        <info>php %command.full_name% --dry-run</info>
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
        $tasks = $this->scheduler->getTasks()->filter(static fn (TaskInterface $task): bool => Expression::REBOOT_MACRO === $task->getExpression());

        $table = new Table($output);
        $table->setHeaders(['Name', 'Type', 'State', 'Tags']);

        $dryRun = $input->getOption('dry-run');

        if (true === $dryRun) {
            if (0 === $tasks->count()) {
                $symfonyStyle->warning([
                    'The scheduler does not contain any tasks',
                    'Be sure that the tasks use the "@reboot" expression',
                ]);

                return self::SUCCESS;
            }

            $table->addRows(array_map(static fn (TaskInterface $task): array => [
                $task->getName(),
                get_class($task),
                $task->getState(),
                implode(', ', $task->getTags()),
            ], $tasks->toArray()));

            $symfonyStyle->success('The following tasks will be executed when the scheduler will reboot:');
            $table->render();

            return self::SUCCESS;
        }

        $this->scheduler->reboot();

        if (0 === $tasks->count()) {
            $symfonyStyle->success('The scheduler have been rebooted, no tasks have been executed');

            return self::SUCCESS;
        }

        while ($this->worker->isRunning()) {
            $symfonyStyle->warning([
                'The scheduler cannot be rebooted as the worker is not available,',
                'The process will be retried as soon as the worker is available',
            ]);
        }

        $this->eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber($tasks->count(), $this->logger));

        $this->worker->execute(
            WorkerConfiguration::create(),
            ...$tasks->toArray(false)
        );

        $symfonyStyle->success('The scheduler have been rebooted, the following tasks have been executed');

        $table->addRows(array_map(static fn (TaskInterface $task): array => [
            $task->getName(),
            get_class($task),
            $task->getState(),
            implode(', ', $task->getTags()),
        ], $tasks->toArray()));

        $table->render();

        return self::SUCCESS;
    }
}
