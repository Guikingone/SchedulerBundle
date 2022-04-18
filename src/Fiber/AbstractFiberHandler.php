<?php

declare(strict_types=1);

namespace SchedulerBundle\Fiber;

use Closure;
use Fiber;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use function sprintf;

abstract class AbstractFiberHandler
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    protected function handleOperationViaFiber(Closure $func): mixed
    {
        $fiber = new Fiber(function (Closure $operation): void {
            $value = $operation();

            Fiber::suspend($value);
        });

        try {
            $return = $fiber->start($func);
        } catch (Throwable $throwable) {
            $this->logger->critical(sprintf('An error occurred while performing the action: %s', $throwable->getMessage()));

            throw $throwable;
        }

        return $return;
    }
}
