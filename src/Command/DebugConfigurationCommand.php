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
    protected static $defaultName = 'scheduler:debug:configuration';

    public function __construct(private ConfigurationInterface $configuration)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription(description: 'Display the current transport configuration keys and values')
            ->setHelp(
                help:
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
        $style = new SymfonyStyle(input: $input, output: $output);

        $style->info(message: sprintf('Found %d keys', $this->configuration->count()));

        $table = new Table(output: $output);
        $table->setHeaders(headers: ['Key', 'Value']);
        $this->configuration->walk(func: static function (string|bool|float|int $value, string $key) use ($table): void {
            $table->addRow(row: [$key, $value]);
        });

        $table->render();

        return Command::SUCCESS;
    }
}
