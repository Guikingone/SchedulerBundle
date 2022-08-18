<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use function array_unique;

use SchedulerBundle\Task\TaskInterface;

use SchedulerBundle\Worker\WorkerInterface;

use const SORT_REGULAR;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerMiddlewareStack extends AbstractMiddlewareStack implements WorkerMiddlewareStackInterface
{
    /**
     * {@inheritdoc}
     */
    public function runPreExecutionMiddleware(TaskInterface $task): void
    {
        $this->runMiddleware(middlewareList: $this->getPreExecutionMiddleware(), func: static function (PreExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->preExecute(task: $task);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function runPostExecutionMiddleware(TaskInterface $task, WorkerInterface $worker): void
    {
        $this->runMiddleware(middlewareList: $this->getPostExecutionMiddleware(), func: static function (PostExecutionMiddlewareInterface $middleware) use ($task, $worker): void {
            $middleware->postExecute(task: $task, worker: $worker);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewareList(): array
    {
        return array_unique(array: [
            ...$this->getPreExecutionMiddleware()->toArray(),
            ...$this->getPostExecutionMiddleware()->toArray(),
        ], flags: SORT_REGULAR);
    }
}
