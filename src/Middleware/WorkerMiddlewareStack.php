<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;
use function array_merge;
use function array_unique;
use const SORT_REGULAR;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerMiddlewareStack extends AbstractMiddlewareStack
{
    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     * @throws Throwable {@see AbstractMiddlewareStack::runMiddleware()}
     */
    public function runPreExecutionMiddleware(TaskInterface $task): void
    {
        $this->runMiddleware($this->getPreExecutionMiddleware(), static function (PreExecutionMiddlewareInterface $middleware) use ($task): void {
            $middleware->preExecute($task);
        });
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     * @throws Throwable {@see AbstractMiddlewareStack::runMiddleware()}
     */
    public function runPostExecutionMiddleware(TaskInterface $task, WorkerInterface $worker): void
    {
        $this->runMiddleware($this->getPostExecutionMiddleware(), static function (PostExecutionMiddlewareInterface $middleware) use ($task, $worker): void {
            $middleware->postExecute($task, $worker);
        });
    }

    /**
     * @return PreExecutionMiddlewareInterface[]|PostExecutionMiddlewareInterface[]|OrderedMiddlewareInterface[]|RequiredMiddlewareInterface[]
     */
    public function getMiddlewareList(): array
    {
        return array_unique(array_merge($this->getPreExecutionMiddleware(), $this->getPostExecutionMiddleware()), SORT_REGULAR);
    }
}
