<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use Throwable;
use function array_merge;
use const SORT_REGULAR;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerMiddlewareStack extends AbstractMiddlewareStack
{
    /**
     * @throws Throwable {@see PreSchedulingMiddlewareInterface::preScheduling()}
     */
    public function runPreSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->runMiddleware($this->getPreSchedulingMiddleware(), function (PreSchedulingMiddlewareInterface $middleware) use ($task, $scheduler): void {
            $middleware->preScheduling($task, $scheduler);
        });
    }

    /**
     * @throws Throwable {@see PostSchedulingMiddlewareInterface::postScheduling()}
     */
    public function runPostSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->runMiddleware($this->getPostSchedulingMiddleware(), function (PostSchedulingMiddlewareInterface $middleware) use ($task, $scheduler): void {
            $middleware->postScheduling($task, $scheduler);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewareList(): array
    {
        return array_unique(array_merge($this->getPreSchedulingMiddleware(), $this->getPostSchedulingMiddleware()), SORT_REGULAR);
    }
}
