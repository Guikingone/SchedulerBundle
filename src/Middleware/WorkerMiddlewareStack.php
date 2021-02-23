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
        $middlewares = $this->getPreExecutionMiddleware();

        $this->runMiddleware($middlewares, function (PreExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->preExecute($task);
        });
    }

    public function runPostExecutionMiddleware(TaskInterface $task): void
    {
        $middlewares = $this->getPostExecutionMiddleware();

        $this->runMiddleware($middlewares, function (PostExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->postExecute($task);
        });
    }
}
