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
     * @throws Throwable {@see AbstractMiddlewareStack::runMiddleware()}
     */
    public function runPreSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->runMiddleware($this->getPreSchedulingMiddleware(), static function (PreSchedulingMiddlewareInterface $middleware) use ($task, $scheduler): void {
            $middleware->preScheduling($task, $scheduler);
        });
    }

    /**
     * @throws Throwable {@see PostSchedulingMiddlewareInterface::postScheduling()}
     * @throws Throwable {@see AbstractMiddlewareStack::runMiddleware()}
     */
    public function runPostSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->runMiddleware($this->getPostSchedulingMiddleware(), static function (PostSchedulingMiddlewareInterface $middleware) use ($task, $scheduler): void {
            $middleware->postScheduling($task, $scheduler);
        });
    }

    /**
     * @return PreSchedulingMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[]|RequiredMiddlewareInterface[]
     */
    public function getMiddlewareList(): array
    {
        return array_unique(array_merge($this->getPreSchedulingMiddleware(), $this->getPostSchedulingMiddleware()), SORT_REGULAR);
    }
}
