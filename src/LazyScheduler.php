<?php

declare(strict_types=1);

namespace SchedulerBundle;

use Closure;
use DateTimeZone;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyScheduler implements SchedulerInterface, LazyInterface
{
    private SchedulerInterface $sourceScheduler;
    private SchedulerInterface $scheduler;
    private bool $initialized = false;

    public function __construct(SchedulerInterface $sourceScheduler)
    {
        $this->sourceScheduler = $sourceScheduler;
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        $this->initialize();

        $this->scheduler->schedule($task);
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        $this->initialize();

        $this->scheduler->unschedule($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        $this->initialize();

        $this->scheduler->yieldTask($name, $async);
    }

    /**
     * {@inheritdoc}
     */
    public function preempt(string $taskToPreempt, Closure $filter): void
    {
        $this->initialize();

        $this->scheduler->preempt($taskToPreempt, $filter);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task, bool $async = false): void
    {
        if ($this->initialized) {
            $this->scheduler->update($taskName, $task, $async);

            return;
        }

        $this->sourceScheduler->update($taskName, $task, $async);

        $this->initialize();
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName, bool $async = false): void
    {
        $this->initialize();

        $this->scheduler->pause($taskName, $async);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->initialize();

        $this->scheduler->resume($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(bool $lazy = false): TaskListInterface
    {
        $this->initialize();

        return $this->scheduler->getTasks($lazy);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(bool $lazy = false, bool $strict = false): TaskListInterface
    {
        $this->initialize();

        return $this->scheduler->getDueTasks($lazy, $strict);
    }

    /**
     * {@inheritdoc}
     */
    public function next(bool $lazy = false): TaskInterface
    {
        $this->initialize();

        return $this->scheduler->next($lazy);
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
