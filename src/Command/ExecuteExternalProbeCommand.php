<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;

use function sprintf;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsCommand(
    name: 'scheduler:execute:external-probe',
    description: 'Execute the external probes',
)]
final class ExecuteExternalProbeCommand extends Command
{
    public function __construct(
        private SchedulerInterface $scheduler,
        private WorkerInterface $worker
    ) {
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
        $probeTasks->walk(static function (ProbeTask $task) use ($table): void {
            $table->addRow([
                $task->getName(),
                $task->getExternalProbePath(),
                $task->getDelay(),
                $task->getExecutionState() ?? 'Not executed',
            ]);
        });

        $table->render();

        return self::SUCCESS;
    }
}
