<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use function array_merge;
use function array_unique;
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
        $this->runMiddleware($this->getPreExecutionMiddleware(), static function (PreExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->preExecute($task);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function runPostExecutionMiddleware(TaskInterface $task, WorkerInterface $worker): void
    {
        $this->runMiddleware($this->getPostExecutionMiddleware(), static function (PostExecutionMiddlewareInterface $middleware) use ($task, $worker): void {
            $middleware->postExecute($task, $worker);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewareList(): array
    {
        return array_unique(array_merge($this->getPreExecutionMiddleware()->toArray(), $this->getPostExecutionMiddleware()->toArray()), SORT_REGULAR);
    }
}
