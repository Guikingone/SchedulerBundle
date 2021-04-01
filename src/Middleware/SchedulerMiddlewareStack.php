<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerMiddlewareStack extends AbstractMiddlewareStack
{
    public function runPreSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->runMiddleware($this->getPreSchedulingMiddleware(), function (PreSchedulingMiddlewareInterface $middleware) use ($task, $scheduler): void {
            $middleware->preScheduling($task, $scheduler);
        });
    }

    public function runPostSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->runMiddleware($this->getPostSchedulingMiddleware(), function (PostSchedulingMiddlewareInterface $middleware) use ($task, $scheduler): void {
            $middleware->postScheduling($task, $scheduler);
        });
    }
}
