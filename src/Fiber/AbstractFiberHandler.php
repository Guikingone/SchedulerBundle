<?php

declare(strict_types=1);

namespace SchedulerBundle\Fiber;

use Closure;
use DateTimeZone;
use Fiber;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Pool\Configuration\SchedulerConfiguration;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Throwable;

use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractFiberHandler
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    protected function handleOperationViaFiber(Closure $func): TaskListInterface|LazyTaskList|TaskInterface|LazyTask|SchedulerConfiguration|ConfigurationInterface|DateTimeZone|string|float|int|bool|array|null
    {
        $fiber = new Fiber(callback: function (Closure $operation): void {
            $value = $operation();

            Fiber::suspend(value: $value);
        });

        try {
            $return = $fiber->start($func);
        } catch (Throwable $throwable) {
            $this->logger->critical(message: sprintf('An error occurred while performing the action: %s', $throwable->getMessage()));

            throw $throwable;
        }

        return $return;
    }
}
