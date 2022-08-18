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

use function sprintf;

use Throwable;

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

    /**
     * @param Closure $func
     *
     * @return TaskListInterface|LazyTaskList|TaskInterface|LazyTask|SchedulerConfiguration|ConfigurationInterface|DateTimeZone|string|float|int|bool|array<int|string, mixed>|null
     *
     * @throws Throwable
     */
    protected function handleOperationViaFiber(Closure $func): TaskListInterface|LazyTaskList|TaskInterface|LazyTask|SchedulerConfiguration|ConfigurationInterface|DateTimeZone|string|float|int|bool|array|null
    {
        $fiber = new Fiber(callback: static function (Closure $operation): void {
            $value = $operation();

            Fiber::suspend(value: $value);
        });

        try {
            $return = $fiber->start(args: $func);
        } catch (Throwable $throwable) {
            $this->logger->critical(message: sprintf('An error occurred while performing the action: %s', $throwable->getMessage()));

            throw $throwable;
        }

        return $return;
    }
}
