<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Throwable;

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
     * @param array<string, int|string|bool> $options
     *
     * @throws UndefinedRunnerException if no runner capable of running the tasks is found
     * @throws Throwable                {@see SchedulerInterface::getDueTasks()}
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void;

    /**
     * Allow to return a "fork" of the current worker,
     * the final implementation is up to each worker that implement the interface.
     *
     * If required, the fact that a given worker instance is a fork can be checked via {@see WorkerInterface::getOptions()}
     */
    public function fork(): WorkerInterface;

    public function stop(): void;

    public function restart(): void;

    public function isRunning(): bool;

    /**
     * @return TaskListInterface<string|int, TaskInterface> which contain every task that has fail during execution.
     *
     * Every task in this list can also be retrieved independently thanks to {@see TaskFailedEvent}.
     *
     * @return TaskListInterface<string, FailedTask>
     */
    public function getFailedTasks(): TaskListInterface;

    /**
     * @return TaskInterface|null The latest executed task or null if the worker is just getting started
     */
    public function getLastExecutedTask(): ?TaskInterface;

    /**
     * @return RunnerInterface[]
     */
    public function getRunners(): array;

    /**
     * @return array<string, bool|int|null|TaskInterface>
     */
    public function getOptions(): ?array;
}
