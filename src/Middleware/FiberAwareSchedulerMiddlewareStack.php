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
        parent::__construct(logger: $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function runPreSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->handleOperationViaFiber(func: function () use ($task, $scheduler): void {
            $this->middlewareStack->runPreSchedulingMiddleware(task: $task, scheduler: $scheduler);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function runPostSchedulingMiddleware(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $this->handleOperationViaFiber(func: function () use ($task, $scheduler): void {
            $this->middlewareStack->runPostSchedulingMiddleware(task: $task, scheduler: $scheduler);
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
