<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkerInterface
{
    /**
     * Execute the given task, if the task cannot be executed, the worker SHOULD exit.
     *
     * An exception can be throw during the execution of the task, if so, it SHOULD be handled.
     *
     * A worker COULD dispatch the following events:
     *  - {@see WorkerStartedEvent}: Contain the worker instance BEFORE executing the task.
     *  - {@see TaskExecutingEvent}: Contain the task to executed BEFORE executing the task.
     *  - {@see TaskExecutedEvent}:  Contain the task to executed AFTER executing the task and its output (if defined).
     *  - {@see TaskFailedEvent}:    Contain the task that failed {@see FailedTask}.
     *  - {@see WorkerOutputEvent}:  Contain the worker instance, the task and the {@see Output} after the execution.
     *  - {@see WorkerStoppedEvent}: Contain the worker instance AFTER executing the task.
     *
     * @param array<string, int|string> $options
     *
     * @throws UndefinedRunnerException if no runner capable of running the tasks is found
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void;

    public function stop(): void;

    /**
     * Restart the worker, the actual restart process is dependant on the current implementation and/or context.
     *
     * Once the worker has been restarted, the {@see WorkerRestartedEvent} must be dispatched.
     */
    public function restart(): void;

    /**
     * Determine if the worker is currently running (aka executing a task / set of tasks).
     *
     * The way the worker determine this informations is up to the worker / current context.
     */
    public function isRunning(): bool;

    /**
     * Return a list which contain every task that has fail during execution.
     *
     * Every task in this list can also be retrieved independently thanks to {@see TaskFailedEvent}.
     */
    public function getFailedTasks(): TaskListInterface;

    /**
     * @return TaskInterface|null The latest executed task or null if none has been executed.
     */
    public function getLastExecutedTask(): ?TaskInterface;

    /**
     * @return array<string, mixed>|null
     */
    public function getOptions(): ?array;
}
