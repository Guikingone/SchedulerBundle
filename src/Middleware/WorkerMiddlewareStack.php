<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerMiddlewareStack extends AbstractMiddlewareStack
{
    /**
     * @throws Throwable {@see PostWorkerStartMiddlewareInterface::postWorkerStart()}
     */
    public function runPostWorkerStartMiddleware(TaskListInterface $taskList): void
    {
        $this->runMiddleware($this->getPostWorkerStartMiddleware(), function (PostWorkerStartMiddlewareInterface $middleware) use ($taskList): void {
            $middleware->postWorkerStart($taskList);
        });
    }

    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     */
    public function runPreExecutionMiddleware(TaskInterface $task): void
    {
        $this->runMiddleware($this->getPreExecutionMiddleware(), function (PreExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->preExecute($task);
        });
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function runPostExecutionMiddleware(TaskInterface $task): void
    {
        $this->runMiddleware($this->getPostExecutionMiddleware(), function (PostExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->postExecute($task);
        });
    }
}
