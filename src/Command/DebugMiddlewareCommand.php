<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DebugMiddlewareCommand extends Command
{
    private SchedulerMiddlewareStack $schedulerMiddlewareStack;
    private WorkerMiddlewareStack $workerMiddlewareStack;

    /**
     * @var string|null
     */
    protected static $defaultName = 'scheduler:debug:middleware';

    public function __construct(
        SchedulerMiddlewareStack $schedulerMiddlewareStack,
        WorkerMiddlewareStack $workerMiddlewareStack
    ) {
        $this->schedulerMiddlewareStack = $schedulerMiddlewareStack;
        $this->workerMiddlewareStack = $workerMiddlewareStack;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        return self::SUCCESS;
    }
}
