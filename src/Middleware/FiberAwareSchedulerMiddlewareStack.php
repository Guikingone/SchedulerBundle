<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use SchedulerBundle\Fiber\AbstractFiberHandler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberAwareSchedulerMiddlewareStack extends AbstractFiberHandler implements SchedulerMiddlewareStackInterface
{
    public function __construct(
        private SchedulerMiddlewareStackInterface $middlewareStack,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritdoc}
     */
    public function runPreSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->handleOperationViaFiber(function () use ($task, $scheduler): void {
            $this->middlewareStack->runPreSchedulingMiddleware($task, $scheduler);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function runPostSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->handleOperationViaFiber(function () use ($task, $scheduler): void {
            $this->middlewareStack->runPostSchedulingMiddleware($task, $scheduler);
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
