<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner\Assets;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BarCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'app:bar';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasArgument('name')) {
            $output->write(sprintf('This command has the "%s" name', $input->getArgument('name')));

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }
}
