<?php

declare(strict_types=1);

namespace SchedulerBundle\CacheClearer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerCacheClearer implements CacheClearerInterface
{
    private SchedulerInterface $scheduler;
    private LoggerInterface $logger;

    public function __construct(
        SchedulerInterface $scheduler,
        ?LoggerInterface $logger = null
    ) {
        $this->scheduler = $scheduler;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function clear(string $cacheDir): void
    {
        try {
            $tasks = $this->scheduler->getTasks();

            $tasks->walk(function (TaskInterface $task): void {
                $this->scheduler->unschedule($task->getName());
            });
        } catch (Throwable $throwable) {
            $this->logger->warning('The cache clearer cannot be called due to an error when retrieving tasks');
        }
    }
}
