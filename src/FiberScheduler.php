<?php

declare(strict_types=1);

namespace SchedulerBundle;

use Closure;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Fiber\AbstractFiberHandler;
use SchedulerBundle\Pool\Configuration\SchedulerConfiguration;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\LockedTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberScheduler extends AbstractFiberHandler implements SchedulerInterface
{
    public function __construct(
        private SchedulerInterface $scheduler,
        protected ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function schedule(TaskInterface $task): void
    {
        $this->handleOperationViaFiber(func: function () use ($task): void {
            $this->scheduler->schedule(task: $task);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function unschedule(string $taskName): void
    {
        $this->handleOperationViaFiber(func: function () use ($taskName): void {
            $this->scheduler->unschedule(taskName: $taskName);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        $this->handleOperationViaFiber(func: function () use ($name, $async): void {
            $this->scheduler->yieldTask(name: $name, async: $async);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function preempt(string $taskToPreempt, Closure $filter): void
    {
        $this->handleOperationViaFiber(func: function () use ($taskToPreempt, $filter): void {
            $this->scheduler->preempt(taskToPreempt: $taskToPreempt, filter: $filter);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function update(string $taskName, TaskInterface $task, bool $async = false): void
    {
        $this->handleOperationViaFiber(func: function () use ($taskName, $task, $async): void {
            $this->scheduler->update(taskName: $taskName, task: $task, async: $async);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function pause(string $taskName, bool $async = false): void
    {
        $this->handleOperationViaFiber(func: function () use ($taskName, $async): void {
            $this->scheduler->pause(taskName: $taskName, async: $async);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function resume(string $taskName): void
    {
        $this->handleOperationViaFiber(func: function () use ($taskName): void {
            $this->scheduler->resume(taskName: $taskName);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function getTasks(bool $lazy = false): TaskListInterface|LazyTaskList
    {
        return $this->handleOperationViaFiber(func: fn (): TaskListInterface|LazyTaskList => $this->scheduler->getTasks(lazy: $lazy));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function getDueTasks(bool $lazy = false, bool $strict = false, bool $lock = false): TaskListInterface|LazyTaskList|LockedTaskList
    {
        return $this->handleOperationViaFiber(func: fn (): TaskListInterface|LazyTaskList|LockedTaskList => $this->scheduler->getDueTasks(lazy: $lazy, strict: $strict, lock: $lock));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function next(bool $lazy = false): TaskInterface|LazyTask
    {
        return $this->handleOperationViaFiber(func: fn (): TaskInterface|LazyTask => $this->scheduler->next(lazy: $lazy));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function reboot(): void
    {
        $this->handleOperationViaFiber(func: function (): void {
            $this->scheduler->reboot();
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function getTimezone(): DateTimeZone
    {
        return $this->handleOperationViaFiber(func: fn (): DateTimeZone => $this->scheduler->getTimezone());
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function getPoolConfiguration(): SchedulerConfiguration
    {
        return $this->handleOperationViaFiber(func: fn (): SchedulerConfiguration => $this->scheduler->getPoolConfiguration());
    }
}
