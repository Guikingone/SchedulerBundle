<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DebugConfigurationCommand extends Command
{
    private ConfigurationInterface $configuration;

    protected static $defaultName = 'scheduler:debug:configuration';

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Display the current transport configuration keys and values')
            ->setHelp(
                <<<'EOF'
                    The <info>%command.name%</info> command display the current configuration.

                        <info>php %command.full_name%</info>
                    EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $style->info(sprintf('Found %d keys', $this->configuration->count()));

        $table = new Table($output);
        $table->setHeaders(['Key', 'Value']);
        $this->configuration->walk(static function ($value, string $key) use ($table): void {
            $table->addRow([$key, $value]);
        });

        $table->render();

        return Command::SUCCESS;
    }
}
