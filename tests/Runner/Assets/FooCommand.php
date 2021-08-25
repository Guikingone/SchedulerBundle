<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner\Assets;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FooCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'app:foo';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL)
            ->addOption('wait', 'w', InputOption::VALUE_NONE)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $input->getOption('env');
        if ('' !== $env && null !== $env) {
            $output->write(sprintf('This command is executed in "%s" env', $env));
        }

        if (true === $input->getOption('wait')) {
            $output->write('This command will wait');
        }

        return self::SUCCESS;
    }
}
