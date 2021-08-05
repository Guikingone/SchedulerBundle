<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUpdateMiddleware implements PostExecutionMiddlewareInterface, OrderedMiddlewareInterface, RequiredMiddlewareInterface
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
        $this->scheduler->update($task->getName(), $task);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 10;
    }
}
