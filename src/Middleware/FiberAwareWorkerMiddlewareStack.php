<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use SchedulerBundle\Fiber\AbstractFiberHandler;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberAwareWorkerMiddlewareStack extends AbstractFiberHandler implements WorkerMiddlewareStackInterface
{
    public function __construct(
        private readonly WorkerMiddlewareStackInterface $middlewareStack,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct(logger: $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function runPreExecutionMiddleware(TaskInterface $task): void
    {
        $this->handleOperationViaFiber(func: function () use ($task): void {
            $this->middlewareStack->runPreExecutionMiddleware(task: $task);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function runPostExecutionMiddleware(TaskInterface $task, WorkerInterface $worker): void
    {
        $this->handleOperationViaFiber(func: function () use ($task, $worker): void {
            $this->middlewareStack->runPostExecutionMiddleware(task: $task, worker: $worker);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function getMiddlewareList(): array
    {
        return $this->handleOperationViaFiber(func: fn (): array => $this->middlewareStack->getMiddlewareList());
    }
}
