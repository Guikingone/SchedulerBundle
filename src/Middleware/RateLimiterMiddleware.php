<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SplQueue;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RateLimiterMiddleware implements PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface
{
    private ?RateLimiterFactory $rateLimiter;
    private SplQueue $limiterQueue;

    public function __construct(?RateLimiterFactory $rateLimiter = null)
    {
        $this->rateLimiter = $rateLimiter;
        $this->limiterQueue = new SplQueue();
    }

    public function preExecute(TaskInterface $task): void
    {
        if (null === $this->rateLimiter) {
            return;
        }

        if (null === $task->getMaxExecution()) {
            return;
        }

        $limiter = $this->rateLimiter->create($task->getName());
        $reservation = $limiter->reserve($task->getMaxExecution());

        $this->limiterQueue->add($task->getName(), [
            'limiter' => $limiter,
            'reservation' => $reservation,
        ]);
    }

    public function postExecute(TaskInterface $task): void
    {
        if (null === $this->rateLimiter) {
            return;
        }

        if (null === $task->getMaxExecution()) {
            return;
        }

        if (!$this->limiterQueue->offsetExists($task->getName())) {
            return;
        }

        $metadata = $this->limiterQueue->offsetGet($task->getName());
        $metadata['limiter']->consume()->ensureAccepted();

        $this->limiterQueue->offsetSet($task->getName(), $metadata);
    }
}
