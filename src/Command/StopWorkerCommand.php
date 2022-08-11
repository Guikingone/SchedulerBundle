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
    description: 'Stops the scheduler worker after the current task'
)]
final class StopWorkerCommand extends Command
{
    public function __construct(private CacheItemPoolInterface $stopWorkerCacheItemPool)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        try {
            $item = $this->stopWorkerCacheItemPool->getItem(key: StopWorkerOnNextTaskSubscriber::STOP_NEXT_TASK_TIMESTAMP_KEY);
            $item->set(value: microtime(as_float: true));

            $this->stopWorkerCacheItemPool->save(item: $item);
        } catch (Throwable $e) {
            $style->error([
                'An error occurred while trying to stop the worker:',
                $e->getMessage()
            ]);

            return Command::FAILURE;
        }

        $style->success('The worker will be stopped after the current task');

        return Command::SUCCESS;
    }
}
