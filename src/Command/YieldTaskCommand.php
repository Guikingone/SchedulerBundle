<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\SchedulerInterface;
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
final class YieldTaskCommand extends Command
{
    private SchedulerInterface $scheduler;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:yield';

    public function __construct(SchedulerInterface $scheduler)
    {
        $this->scheduler = $scheduler;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Yield a task')
            ->setDefinition([
                new InputArgument('name', InputArgument::REQUIRED, 'The task to yield'),
                new InputOption('async', 'a', InputOption::VALUE_NONE, 'Yield the task using the message bus'),
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation without confirmation'),
            ])
            ->setHelp(
                <<<'EOF'
                    The <info>%command.name%</info> command yield a task.

                        <info>php %command.full_name%</info>

                    Use the name argument to specify the task to yield:
                        <info>php %command.full_name% <name></info>

                    Use the --async option to perform the yield using the message bus:
                        <info>php %command.full_name% <name> --async</info>

                    Use the --force option to force the task yield without asking for confirmation:
                        <info>php %command.full_name% <name> --force</info>
                    EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $force = $input->getOption('force');

        if (true === $force || $symfonyStyle->confirm('Do you want to yield this task?', false)) {
            try {
                $this->scheduler->yieldTask($name, $input->getOption('async'));
            } catch (Throwable $throwable) {
                $symfonyStyle->error([
                    'An error occurred when trying to yield the task:',
                    $throwable->getMessage(),
                ]);

                return self::FAILURE;
            }

            $symfonyStyle->success(sprintf('The task "%s" has been yielded', $name));

            return self::SUCCESS;
        }

        $symfonyStyle->warning(sprintf('The task "%s" has not been yielded', $name));

        return self::FAILURE;
    }
}
