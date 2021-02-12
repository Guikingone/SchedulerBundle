<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerMiddlewareHub implements MiddlewareHubInterface
{
    private iterable $middlewares;

    /**
     * @param iterable|PreExecutionMiddlewareInterface[]|PostExecutionMiddlewareInterface[] $middlewares
     */
    public function __construct(iterable $middlewares = [])
    {
        $this->middlewares = $middlewares;
    }

    public function runPreExecutionMiddleware(TaskInterface $task): void
    {
        $middlewares = array_filter($this->middlewares, fn ($middleware): bool => $middleware instanceof PreExecutionMiddlewareInterface);

        array_walk($middlewares, function (PreExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->preExecute($task);
        });
    }

    public function runPostExecutionMiddleware(TaskInterface $task): void
    {
        $middlewares = array_filter($this->middlewares, fn ($middleware): bool => $middleware instanceof PostExecutionMiddlewareInterface);

        array_walk($middlewares, function (PostExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->postExecute($task);
        });
    }
}
