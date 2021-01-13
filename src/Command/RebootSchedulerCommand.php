<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\Expression\ExpressionFactory;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class RebootSchedulerCommand extends Command
{
    private $scheduler;
    private $worker;
    private $eventDispatcher;
    private $logger;

    protected static $defaultName = 'scheduler:reboot';

    public function __construct(SchedulerInterface $scheduler, WorkerInterface $worker, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger = null)
    {
        $this->scheduler = $scheduler;
        $this->worker = $worker;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;

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
            ->setHelp(<<<'EOF'
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
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('dry-run')) {
            $tasks = $this->scheduler->getTasks()->filter(function (TaskInterface $task): bool {
                return ExpressionFactory::REBOOT_MACRO === $task->getExpression();
            });
            if (empty($tasks)) {
                $io->warning('The scheduler does not contain any tasks planned for the reboot process');

                return self::SUCCESS;
            }

            $table = new Table($output);
            $table->setHeaders(['Name', 'Type', 'State', 'Tags']);

            foreach ($tasks as $task) {
                $table->addRow([$task->getName(), \get_class($task), $task->getState(), implode(', ', $task->getTags())]);
            }

            $io->success('The following tasks will be executed when the scheduler will reboot:');
            $table->render();

            return self::SUCCESS;
        }

        $this->scheduler->reboot();

        $tasks = $this->scheduler->getTasks()->filter(function (TaskInterface $task): bool {
            return ExpressionFactory::REBOOT_MACRO === $task->getExpression();
        });

        if (0 === $tasks->count()) {
            $io->success('The scheduler have been rebooted, no tasks have been executed');

            return self::SUCCESS;
        }

        while ($this->worker->isRunning()) {
            $io->warning('The scheduler cannot be rebooted as the worker is not available, retrying to access it');

            sleep(1);
        }

        $this->eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber($tasks->count(), $this->logger));

        $this->worker->execute([], ...$tasks);

        $io->success('The scheduler have been rebooted, the following tasks have been executed');

        $table = new Table($output);
        $table->setHeaders(['Name', 'Type', 'State', 'Tags']);

        foreach ($tasks as $task) {
            $table->addRow([$task->getName(), \get_class($task), $task->getState(), implode(', ', $task->getTags())]);
        }

        $table->render();

        return self::SUCCESS;
    }
}
