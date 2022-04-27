<?php

declare(strict_types=1);

namespace SchedulerBundle;

use Closure;
use DateTimeZone;
use SchedulerBundle\Pool\Configuration\SchedulerConfiguration;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyScheduler implements SchedulerInterface, LazyInterface
{
    private SchedulerInterface $scheduler;
    private bool $initialized = false;

    public function __construct(private SchedulerInterface $sourceScheduler)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        $this->initialize();

        $this->scheduler->schedule(task: $task);
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        $this->initialize();

        $this->scheduler->unschedule(taskName: $taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        $this->initialize();

        $this->scheduler->yieldTask(name: $name, async: $async);
    }

    /**
     * {@inheritdoc}
     */
    public function preempt(string $taskToPreempt, Closure $filter): void
    {
        $this->initialize();

        $this->scheduler->preempt(taskToPreempt: $taskToPreempt, filter: $filter);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task, bool $async = false): void
    {
        if ($this->initialized) {
            $this->scheduler->update(taskName: $taskName, task: $task, async: $async);

            return;
        }

        $this->sourceScheduler->update(taskName: $taskName, task: $task, async: $async);

        $this->initialize();
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName, bool $async = false): void
    {
        $this->initialize();

        $this->scheduler->pause(taskName: $taskName, async: $async);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->initialize();

        $this->scheduler->resume(taskName: $taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(bool $lazy = false): TaskListInterface|LazyTaskList
    {
        $this->initialize();

        return $this->scheduler->getTasks(lazy: $lazy);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(bool $lazy = false, bool $strict = false): TaskListInterface|LazyTaskList
    {
        $this->initialize();

        return $this->scheduler->getDueTasks(lazy: $lazy, strict: $strict);
    }

    /**
     * {@inheritdoc}
     */
    public function next(bool $lazy = false): TaskInterface|LazyTask
    {
        $this->initialize();

        return $this->scheduler->next(lazy: $lazy);
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $this->initialize();

        $this->scheduler->reboot();
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): DateTimeZone
    {
        $this->initialize();

        return $this->scheduler->getTimezone();
    }

    /**
     * {@inheritdoc}
     */
    public function getPoolConfiguration(): SchedulerConfiguration
    {
        $this->initialize();

        return $this->scheduler->getPoolConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->scheduler = $this->sourceScheduler;
        $this->initialized = true;
    }
}
