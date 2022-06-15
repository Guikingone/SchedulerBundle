<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\TransportInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUpdateMiddleware implements PostExecutionMiddlewareInterface, OrderedMiddlewareInterface, RequiredMiddlewareInterface
{
    public function __construct(private readonly TransportInterface $transport)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
        $this->transport->update(name: $task->getName(), updatedTask: $task);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 10;
    }
}
