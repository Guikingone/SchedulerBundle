<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecuteExternalProbeCommand extends Command
{
    private SchedulerInterface $scheduler;
    private WorkerInterface $worker;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:execute:external-probe';

    public function __construct(
        SchedulerInterface $scheduler,
        WorkerInterface $worker
    ) {
        $this->scheduler = $scheduler;
        $this->worker = $worker;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $probeTasks = $this->scheduler->getDueTasks()->filter(fn (TaskInterface $task): bool => $task instanceof ProbeTask);
        if (0 === $probeTasks->count()) {
            $style->warning('No external probe found');

            return self::FAILURE;
        }

        try {
            $this->worker->execute([], ...$probeTasks->toArray(false));
        } catch (Throwable $throwable) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
