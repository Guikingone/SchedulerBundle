<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command\Assets;

use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class PostExecutionMiddleware implements PostExecutionMiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
    }
}
