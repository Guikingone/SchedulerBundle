<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\Export\ExporterRegistryInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExportCommand extends Command
{
    private ExporterRegistryInterface $exporterRegistry;
    private SchedulerInterface $scheduler;

    protected static $defaultName = 'scheduler:export';

    public function __construct(
        ExporterRegistryInterface $exporterRegistry,
        SchedulerInterface $scheduler
    ) {
        $this->exporterRegistry = $exporterRegistry;
        $this->scheduler = $scheduler;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Export tasks to a specific format')
            ->setDefinition([
                new InputArgument('format', InputArgument::REQUIRED, 'The format used to export tasks', 'crontab'),
                new InputOption('filename', null, InputOption::VALUE_OPTIONAL, 'The name of the filename used to export tasks', '/etc/cron.d'),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        try {
            $tasks = $this->scheduler->getTasks();
            if (0 === $tasks->count()) {
                $style->warning('No tasks found');

                return Command::FAILURE;
            }

            $filename = $input->getOption('filename');
            $exporter = $this->exporterRegistry->find($input->getArgument('format'));

            $tasks->walk(function (TaskInterface $task) use ($exporter, $filename): void {
                $exporter->export($filename, $task);
            });
        } catch (Throwable $throwable) {
            $style->error([
                'An error occurred when exporting tasks:',
                $throwable->getMessage(),
            ]);

            return Command::FAILURE;
        }

        $style->success('The export has succeed');

        return Command::SUCCESS;
    }
}
