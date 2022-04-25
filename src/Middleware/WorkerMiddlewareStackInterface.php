<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkerMiddlewareStackInterface
{
    /**
     * @throws Throwable {@see PreExecutionMiddlewareInterface::preExecute()}
     * @throws Throwable {@see AbstractMiddlewareStack::runMiddleware()}
     */
    public function runPreExecutionMiddleware(TaskInterface $task): void;

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     * @throws Throwable {@see AbstractMiddlewareStack::runMiddleware()}
     */
    public function runPostExecutionMiddleware(TaskInterface $task, WorkerInterface $worker): void;
}
