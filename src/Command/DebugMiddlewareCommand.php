<?php

declare(strict_types=1);

namespace SchedulerBundle\Command;

use ReflectionClass;
use SchedulerBundle\Middleware\OrderedMiddlewareInterface;
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\RequiredMiddlewareInterface;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function array_map;
use function count;
use function sprintf;

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
        $this
            ->setDescription('Display the registered middlewares')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $schedulerMiddlewareList = $this->schedulerMiddlewareStack->getMiddlewareList();
        if ([] === $schedulerMiddlewareList) {
            $style->warning('No middleware found for the scheduling phase');
        } else {
            $style->info(sprintf('Found %d middleware for the scheduling phase', count($schedulerMiddlewareList)));

            $schedulerTable = new Table($output);
            $schedulerTable->setHeaders(['Name', 'PreScheduling', 'PostScheduling', 'Priority', 'Required']);
            $schedulerTable->addRows(array_map(static fn (object $middleware): array => [
                (new ReflectionClass($middleware))->getShortName(),
                $middleware instanceof PreSchedulingMiddlewareInterface ? 'Yes' : 'No',
                $middleware instanceof PostSchedulingMiddlewareInterface ? 'Yes' : 'No',
                $middleware instanceof OrderedMiddlewareInterface ? $middleware->getPriority() : 'No',
                $middleware instanceof RequiredMiddlewareInterface ? 'Yes' : 'No',
            ], $schedulerMiddlewareList));

            $schedulerTable->render();
        }

        $workerMiddlewareList = $this->workerMiddlewareStack->getMiddlewareList();
        if ([] === $workerMiddlewareList) {
            $style->warning('No middleware found for the execution phase');
        } else {
            $style->info(sprintf('Found %d middleware for the execution phase', count($workerMiddlewareList)));

            $workerTable = new Table($output);
            $workerTable->setHeaders(['Name', 'PreExecution', 'PostExecution', 'Priority', 'Required']);
            $workerTable->addRows(array_map(static fn (object $middleware): array => [
                (new ReflectionClass($middleware))->getShortName(),
                $middleware instanceof PreExecutionMiddlewareInterface ? 'Yes' : 'No',
                $middleware instanceof PostExecutionMiddlewareInterface ? 'Yes' : 'No',
                $middleware instanceof OrderedMiddlewareInterface ? $middleware->getPriority() : 'No',
                $middleware instanceof RequiredMiddlewareInterface ? 'Yes' : 'No',
            ], $workerMiddlewareList));

            $workerTable->render();
        }

        return self::SUCCESS;
    }
}
