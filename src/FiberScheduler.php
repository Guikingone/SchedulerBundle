<?php

declare(strict_types=1);

namespace SchedulerBundle;

use Closure;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Fiber\AbstractFiberHandler;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

final class FiberScheduler extends AbstractFiberHandler implements SchedulerInterface
{
    public function __construct(
        private SchedulerInterface $scheduler,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        $this->handleOperationViaFiber(function () use ($task): void {
            $this->scheduler->schedule($task);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        $this->handleOperationViaFiber(function () use ($taskName): void {
            $this->scheduler->unschedule($taskName);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        $this->handleOperationViaFiber(function () use ($name, $async): void {
            $this->scheduler->yieldTask($name, $async);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function preempt(string $taskToPreempt, Closure $filter): void
    {
        $this->handleOperationViaFiber(function () use ($taskToPreempt, $filter): void {
            $this->scheduler->preempt($taskToPreempt, $filter);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task, bool $async = false): void
    {
        $this->handleOperationViaFiber(function () use ($taskName, $task, $async): void {
            $this->scheduler->update($taskName, $task, $async);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName, bool $async = false): void
    {
        $this->handleOperationViaFiber(function () use ($taskName, $async): void {
            $this->scheduler->pause($taskName, $async);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->handleOperationViaFiber(function () use ($taskName): void {
            $this->scheduler->resume($taskName);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(bool $lazy = false): TaskListInterface
    {
        return $this->handleOperationViaFiber(fn (): TaskListInterface => $this->scheduler->getTasks($lazy));
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(bool $lazy = false, bool $strict = false): TaskListInterface
    {
        return $this->handleOperationViaFiber(fn (): TaskListInterface => $this->scheduler->getDueTasks($lazy, $strict));
    }

    /**
     * {@inheritdoc}
     */
    public function next(bool $lazy = false): TaskInterface
    {
        return $this->handleOperationViaFiber(fn (): TaskInterface => $this->scheduler->next($lazy));
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $this->handleOperationViaFiber(function (): void {
            $this->scheduler->reboot();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): DateTimeZone
    {
        return $this->handleOperationViaFiber(function (): void {
            $this->scheduler->getTimezone();
        });
    }
}
