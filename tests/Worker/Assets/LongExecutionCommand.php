<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker\Assets;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function sleep;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongExecutionCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'app:long';

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        sleep(5);

        return self::SUCCESS;
    }
}
