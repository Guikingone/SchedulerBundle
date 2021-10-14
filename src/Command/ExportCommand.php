<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\Export\ExporterRegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExportCommand extends Command
{
    private ExporterRegistryInterface $exporterRegistry;

    protected static $defaultName = 'scheduler:export';

    public function __construct(ExporterRegistryInterface $exporterRegistry)
    {
        $this->exporterRegistry = $exporterRegistry;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Export tasks to a specific format')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        // TODO

        return Command::SUCCESS;
    }
}
