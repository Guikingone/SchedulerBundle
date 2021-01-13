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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class ListFailedTasksCommand extends Command
{
    private $worker;

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
        if (empty($failedTasksList)) {
            $style->warning('No failed task has been found');

            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Expression', 'Reason', 'Date']);

        $failedTasks = [];
        foreach ($failedTasksList as $task) {
            $failedTasks[] = [$task->getName(), $task->getTask()->getExpression(), $task->getReason(), $task->getFailedAt()->format(\DATE_ATOM)];
        }

        $table->addRows($failedTasks);

        $style->success(sprintf('%d task%s found', \count($failedTasks), \count($failedTasks) > 1 ? 's' : ''));
        $table->render();

        return self::SUCCESS;
    }
}
