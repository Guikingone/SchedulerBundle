<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;
use function array_merge;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerMiddlewareStack extends AbstractMiddlewareStack
{
    /**
     * @throws Throwable {@see PostWorkerStartMiddlewareInterface::postWorkerStart()}
     */
    public function runPostWorkerStartMiddleware(TaskListInterface $taskList, WorkerInterface $worker): void
    {
        $this->runMiddleware($this->getPostWorkerStartMiddleware(), function (PostWorkerStartMiddlewareInterface $middleware) use ($taskList, $worker): void {
            $middleware->postWorkerStart($taskList, $worker);
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
    public function runPostExecutionMiddleware(TaskInterface $task, WorkerInterface $worker): void
    {
        $this->runMiddleware($this->getPostExecutionMiddleware(), function (PostExecutionMiddlewareInterface $middleware) use ($task, $worker): void {
            $middleware->postExecute($task, $worker);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewareList(): array
    {
        return array_merge($this->getPostWorkerStartMiddleware(), $this->getPreExecutionMiddleware(), $this->getPostExecutionMiddleware());
    }
}
