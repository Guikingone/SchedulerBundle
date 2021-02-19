<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Exception\MiddlewareException;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use function is_null;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RateLimiterMiddleware implements PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface
{
    private ?RateLimiterFactory $rateLimiter;
    private LoggerInterface $logger;

    public function __construct(
        ?RateLimiterFactory $rateLimiter = null,
        ?LoggerInterface $logger = null
    ) {
        $this->rateLimiter = $rateLimiter;
        $this->logger = $logger ?: new NullLogger();
    }

    public function preExecute(TaskInterface $task): void
    {
        if (is_null($this->rateLimiter)) {
            return;
        }

        if (is_null($task->getMaxExecution())) {
            return;
        }

        $limiter = $this->rateLimiter->create($task->getName());

        try {
            $limiter->reserve($task->getMaxExecution());
        } catch (ReserveNotSupportedException $exception) {
            $this->logger->critical(sprintf(
                'A reservation cannot be created for task "%s", please ensure that the policy used supports it.',
                $task->getName()
            ));

            throw new MiddlewareException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function postExecute(TaskInterface $task): void
    {
        if (is_null($this->rateLimiter)) {
            return;
        }

        if (is_null($task->getMaxExecution())) {
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

            throw new MiddlewareException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
