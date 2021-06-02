<?php

declare(strict_types=1);

namespace SchedulerBundle\CacheClearer;

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

    public function __construct(SchedulerInterface $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function clear(string $cacheDir): void
    {
        $tasks = $this->scheduler->getTasks();

        $tasks->walk(function (TaskInterface $task): void {
            $this->scheduler->unschedule($task->getName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isOptional(): bool
    {
        return true;
    }
}
