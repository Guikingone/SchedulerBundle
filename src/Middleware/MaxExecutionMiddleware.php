<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Exception\MiddlewareException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MaxExecutionMiddleware implements PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface
{
    private ?RateLimiterFactory $rateLimiter;
    private LoggerInterface $logger;

    public function __construct(
        ?RateLimiterFactory $rateLimiterFactory = null,
        ?LoggerInterface $logger = null
    ) {
        $this->rateLimiter = $rateLimiterFactory;
        $this->logger = $logger ?? new NullLogger();
    }

    public function preExecute(TaskInterface $task): void
    {
        if (!$this->rateLimiter instanceof RateLimiterFactory) {
            return;
        }

        $maxExecutions = $task->getMaxExecutions();
        if (null === $maxExecutions) {
            return;
        }

        $limiter = $this->rateLimiter->create($task->getName());

        try {
            $limiter->reserve($maxExecutions);
        } catch (ReserveNotSupportedException $exception) {
            $this->logger->critical(sprintf(
                'A reservation cannot be created for task "%s", please ensure that the policy used supports it.',
                $task->getName()
            ));

            throw new MiddlewareException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
        if (!$this->rateLimiter instanceof RateLimiterFactory) {
            return;
        }

        $maxExecutions = $task->getMaxExecutions();
        if (null === $maxExecutions) {
            return;
        }

        $limiter = $this->rateLimiter->create($task->getName());

        try {
            $limiter->consume()->ensureAccepted();
        } catch (RateLimitExceededException $exception) {
            $this->logger->critical(sprintf(
                'The execution limit for task "%s" has been exceeded',
                $task->getName()
            ));

            throw new MiddlewareException($exception->getMessage(), 0, $exception);
        }
    }
}
