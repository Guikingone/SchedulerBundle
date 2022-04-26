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
        private WorkerMiddlewareStackInterface $middlewareStack,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritdoc}
     */
    public function runPreExecutionMiddleware(TaskInterface $task): void
    {
        $this->handleOperationViaFiber(function () use ($task): void {
            $this->middlewareStack->runPreExecutionMiddleware($task);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function runPostExecutionMiddleware(TaskInterface $task, WorkerInterface $worker): void
    {
        $this->handleOperationViaFiber(function () use ($task, $worker): void {
            $this->middlewareStack->runPostExecutionMiddleware($task, $worker);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function getMiddlewareList(): array
    {
        return $this->handleOperationViaFiber(fn (): array => $this->middlewareStack->getMiddlewareList());
    }
}
