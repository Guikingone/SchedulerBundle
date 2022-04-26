<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SchedulerMiddlewareStackInterface extends MiddlewareStackInterface
{
    /**
     * @throws Throwable {@see PreSchedulingMiddlewareInterface::preScheduling()}
     * @throws Throwable {@see AbstractMiddlewareStack::runMiddleware()}
     */
    public function runPreSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void;

    /**
     * @throws Throwable {@see PostSchedulingMiddlewareInterface::postScheduling()}
     * @throws Throwable {@see AbstractMiddlewareStack::runMiddleware()}
     */
    public function runPostSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void;
}
