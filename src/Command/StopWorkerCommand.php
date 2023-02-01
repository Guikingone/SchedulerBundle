<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\EventListener\StopWorkerOnNextTaskSubscriber;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function microtime;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsCommand(
    name: 'scheduler:stop-worker',
    description: 'Stops the worker after the current task'
)]
final class StopWorkerCommand extends Command
{
    public function __construct(private CacheItemPoolInterface $stopWorkerCacheItemPool)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                help:
                <<<'EOF'
                    The <info>%command.name%</info> command stop the worker after the current task.

                        <info>php %command.full_name%</info>

                    The worker will *not* be restarted once stopped.
                    EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle(input: $input, output: $output);

        try {
            $item = $this->stopWorkerCacheItemPool->getItem(key: StopWorkerOnNextTaskSubscriber::STOP_NEXT_TASK_TIMESTAMP_KEY);
            $item->set(value: microtime(as_float: true));

            $this->stopWorkerCacheItemPool->save(item: $item);
        } catch (Throwable $e) {
            $style->error(message: [
                'An error occurred while trying to stop the worker:',
                $e->getMessage(),
            ]);

            return Command::FAILURE;
        }

        $style->success(message: [
            'The worker will be stopped according the following conditions:',
            ' - The current task has been executed',
            ' - The sleep phase is over',
        ]);

        return Command::SUCCESS;
    }
}
