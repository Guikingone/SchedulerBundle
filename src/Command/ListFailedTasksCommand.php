<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\Task\FailedTask;
use Symfony\Component\Console\Attribute\AsCommand;
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
#[AsCommand(
    name: 'scheduler:list:failed',
    description: 'List all the failed tasks',
)]
final class ListFailedTasksCommand extends Command
{
    public function __construct(private readonly WorkerInterface $worker)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle(input: $input, output: $output);

        $failedTasksList = $this->worker->getFailedTasks();
        if (0 === $failedTasksList->count()) {
            $symfonyStyle->warning(message: 'No failed task has been found');

            return self::SUCCESS;
        }

        $table = new Table(output: $output);
        $table->setHeaders(headers: ['Name', 'Expression', 'Reason', 'Date']);

        $failedTasksList->walk(func: static function (FailedTask $task) use ($table): void {
            $table->addRow(row: [
                $task->getName(),
                $task->getTask()->getExpression(),
                $task->getReason(),
                $task->getFailedAt()->format(DATE_ATOM),
            ]);
        });

        $symfonyStyle->success(message: sprintf('%d task%s found', count(value: $failedTasksList), count(value: $failedTasksList) > 1 ? 's' : ''));
        $table->render();

        return self::SUCCESS;
    }
}
