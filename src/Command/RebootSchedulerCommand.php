<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Worker\WorkerConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
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
use Throwable;
use function implode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsCommand(
    name: 'scheduler:reboot',
    description: 'Reboot the scheduler',
)]
final class RebootSchedulerCommand extends Command
{
    public function __construct(
        private readonly SchedulerInterface $scheduler,
        private readonly WorkerInterface $worker,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputOption(name: 'dry-run', shortcut: 'd', mode: InputOption::VALUE_NONE, description: 'Test the reboot without executing the tasks, the "ready to reboot" tasks are displayed'),
            ])
            ->setHelp(
                help:
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
     *
     * @throws Throwable {@see SchedulerInterface::reboot()}
     * @throws Throwable {@see WorkerInterface::execute()}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle(input: $input, output: $output);
        $toRebootTasks = $this->scheduler->getTasks()->filter(filter: static fn (TaskInterface $task): bool => Expression::REBOOT_MACRO === $task->getExpression());

        $table = new Table(output: $output);
        $table->setHeaders(headers: ['Name', 'Type', 'State', 'Tags']);

        $dryRun = $input->getOption(name: 'dry-run');

        if (true === $dryRun) {
            if (0 === $toRebootTasks->count()) {
                $symfonyStyle->warning(message: [
                    'The scheduler does not contain any tasks',
                    'Be sure that the tasks use the "@reboot" expression',
                ]);

                return self::SUCCESS;
            }

            $toRebootTasks->walk(func: static function (TaskInterface $task) use ($table): void {
                $table->addRow(row: [
                    $task->getName(),
                    $task::class,
                    $task->getState(),
                    implode(separator: ', ', array: $task->getTags()),
                ]);
            });

            $symfonyStyle->success(message: 'The following tasks will be executed when the scheduler will reboot:');
            $table->render();

            return self::SUCCESS;
        }

        $this->scheduler->reboot();

        if (0 === $toRebootTasks->count()) {
            $symfonyStyle->success(message: 'The scheduler have been rebooted, no tasks have been executed');

            return self::SUCCESS;
        }

        while ($this->worker->isRunning()) {
            $symfonyStyle->warning(message: [
                'The scheduler cannot be rebooted as the worker is not available,',
                'The process will be retried as soon as the worker is available',
            ]);
        }

        $this->eventDispatcher->addSubscriber(subscriber: new StopWorkerOnTaskLimitSubscriber(maximumTasks: $toRebootTasks->count(), logger: $this->logger));

        $this->worker->execute(
            WorkerConfiguration::create(),
            ...$toRebootTasks->toArray(keepKeys: false)
        );

        $symfonyStyle->success(message: 'The scheduler have been rebooted, the following tasks have been executed');

        $toRebootTasks->walk(func: static function (TaskInterface $task) use ($table): void {
            $table->addRow(row: [
                $task->getName(),
                $task::class,
                $task->getState(),
                implode(separator: ', ', array: $task->getTags()),
            ]);
        });

        $table->render();

        return self::SUCCESS;
    }
}
