<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SingleRunTaskMiddleware implements PostExecutionMiddlewareInterface, OrderedMiddlewareInterface, RequiredMiddlewareInterface
{
    private SchedulerInterface $scheduler;

    public function __construct(SchedulerInterface $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
        if (!$task->isSingleRun()) {
            return;
        }

        $this->scheduler->pause($task->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 15;
    }
}
