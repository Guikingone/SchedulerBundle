<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use DateTimeImmutable;
use DateTimeInterface;
use SchedulerBundle\Probe\ProbeInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function array_map;
use function count;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DebugProbeCommand extends Command
{
    private ProbeInterface $probe;
    private SchedulerInterface $scheduler;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:debug:probe';

    public function __construct(
        ProbeInterface $probe,
        SchedulerInterface $scheduler
    ) {
        $this->probe = $probe;
        $this->scheduler = $scheduler;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Display the current probe state and the external probes state (if defined)')
            ->setDefinition([
                new InputOption('external', null, InputOption::VALUE_NONE, 'Define if the external probes state must be displayed'),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $style->info(sprintf('The displayed probe state is the one found at %s', (new DateTimeImmutable())->format(DateTimeInterface::W3C)));

        $table = new Table($output);
        $table->setHeaders(['Executed tasks', 'Failed tasks', 'Scheduled tasks']);
        $table->addRow([
            $this->probe->getExecutedTasks(),
            $this->probe->getFailedTasks(),
            $this->probe->getScheduledTasks(),
        ]);

        $table->render();

        if ($input->getOption('external')) {
            $externalProbeTasks = $this->scheduler->getTasks()->filter(fn (TaskInterface $task): bool => $task instanceof ProbeTask);
            if (0 === $externalProbeTasks->count()) {
                $style->warning('No external probe found');

                return self::SUCCESS;
            }

            $style->info(sprintf('Found %s external probe%s', $externalProbeTasks->count(), 1 === count($externalProbeTasks) ? '' : 's'));

            $secondTable = new Table($output);
            $secondTable->setHeaders(['Name', 'Path', 'State', 'Last execution', 'Execution state']);
            $secondTable->addRows(array_map(fn (TaskInterface $task): array => [
                $task->getName(),
                $task->getExternalProbePath(),
                $task->getState(),
                null !== $task->getLastExecution() ? $task->getLastExecution()->format(DateTimeInterface::COOKIE) : 'Not executed',
                null !== $task->getExecutionState() ? $task->getExecutionState() : 'Not executed',
            ], $externalProbeTasks->toArray(false)));

            $secondTable->render();
        }

        return self::SUCCESS;
    }
}
