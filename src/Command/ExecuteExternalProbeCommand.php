<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use function array_map;
use function sprintf;

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
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Execute the external probes')
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $probeTasks = $this->scheduler->getDueTasks()->filter(static fn (TaskInterface $task): bool => $task instanceof ProbeTask);
        if (0 === $probeTasks->count()) {
            $style->warning('No external probe found');

            return self::FAILURE;
        }

        try {
            $this->worker->execute(
                WorkerConfiguration::create(),
                ...$probeTasks->toArray(false)
            );
        } catch (Throwable $throwable) {
            $style->error([
                'An error occurred during the external probe execution:',
                $throwable->getMessage(),
            ]);

            return self::FAILURE;
        }

        $style->success(sprintf('%d external probe%s executed', $probeTasks->count(), 1 === $probeTasks->count() ? '' : 's'));

        $table = new Table($output);
        $table->setHeaders(['Name', 'Path', 'Delay', 'Execution state']);
        $table->addRows(array_map(static fn (ProbeTask $task): array => [
            $task->getName(),
            $task->getExternalProbePath(),
            $task->getDelay(),
            $task->getExecutionState() ?? 'Not executed',
        ], $probeTasks->toArray()));

        $table->render();

        return self::SUCCESS;
    }
}
