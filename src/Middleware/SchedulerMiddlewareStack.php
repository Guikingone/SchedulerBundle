<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

use function array_unique;

use const SORT_REGULAR;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerMiddlewareStack extends AbstractMiddlewareStack implements SchedulerMiddlewareStackInterface
{
    /**
     * {@inheritdoc}
     */
    public function runPreSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->runMiddleware(middlewareList: $this->getPreSchedulingMiddleware(), func: static function (PreSchedulingMiddlewareInterface $middleware) use ($task, $scheduler): void {
            $middleware->preScheduling(task: $task, scheduler: $scheduler);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function runPostSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->runMiddleware(middlewareList: $this->getPostSchedulingMiddleware(), func: static function (PostSchedulingMiddlewareInterface $middleware) use ($task, $scheduler): void {
            $middleware->postScheduling(task: $task, scheduler: $scheduler);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewareList(): array
    {
        $preSchedulingMiddlewareList = $this->getPreSchedulingMiddleware();
        $postSchedulingMiddlewareList = $this->getPostSchedulingMiddleware();

        return array_unique(array: [
            ...$preSchedulingMiddlewareList->toArray(),
            ...$postSchedulingMiddlewareList->toArray(),
        ], flags: SORT_REGULAR);
    }
}
