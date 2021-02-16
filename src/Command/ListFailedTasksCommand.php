<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SchedulerBundle\Worker\WorkerInterface;
use function count;
use function sprintf;
use const DATE_ATOM;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ListFailedTasksCommand extends Command
{
    private WorkerInterface $worker;

    /**
     * @var string
     */
    protected static $defaultName = 'scheduler:list:failed';

    public function __construct(WorkerInterface $worker)
    {
        $this->worker = $worker;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('List all the failed tasks')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $failedTasksList = $this->worker->getFailedTasks()->toArray();
        if ([] === $failedTasksList) {
            $style->warning('No failed task has been found');

            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Expression', 'Reason', 'Date']);

        $failedTasks = [];
        foreach ($failedTasksList as $task) {
            $failedTasks[] = [$task->getName(), $task->getTask()->getExpression(), $task->getReason(), $task->getFailedAt()->format(DATE_ATOM)];
        }

        $table->addRows($failedTasks);

        $style->success(sprintf('%d task%s found', count($failedTasks), count($failedTasks) > 1 ? 's' : ''));
        $table->render();

        return self::SUCCESS;
    }
}
