<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerMiddlewareStack extends AbstractMiddlewareStack
{
    public function runPreExecutionMiddleware(TaskInterface $task): void
    {
        $this->runMiddleware($this->getPreExecutionMiddleware(), function (PreExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->preExecute($task);
        });
    }

    public function runPostExecutionMiddleware(TaskInterface $task): void
    {
        $this->runMiddleware($this->getPostExecutionMiddleware(), function (PostExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->postExecute($task);
        });
    }
}
