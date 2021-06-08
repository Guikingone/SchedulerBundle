<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DebugConfigurationCommand extends Command
{
    private TransportInterface $transport;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:debug:configuration';

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Display the options stored in the current transport configuration')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $configuration = $this->transport->getConfiguration();
        $style->info(sprintf('Found %d configuration key%s', $configuration->count(), 1 === $configuration->count() ? '' : 's'));

        $table = new Table($output);
        $table->setHeaders(['Key', 'Value']);

        $configuration->walk(function ($value, string $key) use ($table): void {
            $table->addRow([$key, $value]);
        });

        $table->render();

        return self::SUCCESS;
    }
}
