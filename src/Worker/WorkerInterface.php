<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Exception;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Runner\RunnerRegistryInterface;
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
     *
     *  - {@see WorkerStartedEvent}: Contain the worker instance BEFORE executing the task.
     *  - {@see TaskExecutingEvent}: Contain the task to executed BEFORE executing the task.
     *  - {@see TaskExecutedEvent}:  Contain the task to executed AFTER executing the task and its output (if defined).
     *  - {@see TaskFailedEvent}:    Contain the task that failed {@see FailedTask}.
     *  - {@see WorkerOutputEvent}:  Contain the worker instance, the task and the {@see Output} after the execution.
     *  - {@see WorkerStoppedEvent}: Contain the worker instance AFTER executing the task.
     *
     * @throws UndefinedRunnerException if no runner capable of running the tasks is found
     * @throws Throwable                {@see SchedulerInterface::getDueTasks()}
     */
    public function execute(WorkerConfiguration $configuration, TaskInterface ...$tasks): void;

    /**
     * Allows to preempt the currently executed task.
     *
     * The preemption is done in an atomic approach:
     *
     *  - The worker retrieves the current tasks
     *  - The worker is paused then forked
     *  - The new worker try to execute the given @param TaskListInterface $preemptTaskList then stop
     *  - The primary worker restart then try to execute the remaining tasks
     *
     * @throws Throwable {@see WorkerInterface::execute()}
     */
    public function preempt(TaskListInterface $preemptTaskList, TaskListInterface $toPreemptTasksList): void;

    /**
     * Allow to return a "fork" of the current worker,
     * the final implementation is up to each worker that implement the interface.
     *
     * If required, the fact that a given worker instance is a fork can be checked via {@see WorkerInterface::getOptions()}
     */
    public function fork(): WorkerInterface;

    /**
     * Stop the worker and reset its internal state.
     */
    /**
     * Pause the worker and store the currently executing task to resume it later when {@see WorkerInterface::restart()} is called.
     *
     * {@internal Be aware that the task can be stored even if the execution has succeed}
     */
    public function pause(): WorkerInterface;

    /**
     * Stop the worker, the internal state can be reset if required, the final implementation is up to the worker.
     */
    public function stop(): void;

    /**
     * Restart the worker, the actual restart process is dependant on the current implementation and/or context.
     *
     * Once the worker has been restarted, the {@see WorkerRestartedEvent} must be dispatched.
     */
    public function restart(): void;

    /**
     * Start a "sleeping" phase in the current worker.
     *
     * @throws Exception {@see DateTimeImmutable::__construct()}
     */
    public function sleep(): void;

    /**
     * Determine if the worker is currently running, the implementation is up to the final class.
     */
    public function isRunning(): bool;

    /**
     * Every task in this list can also be retrieved independently thanks to {@see TaskFailedEvent}.
     */
    public function getFailedTasks(): TaskListInterface;

    /**
     * @return TaskInterface|null The latest executed task or null if none has been executed.
     */
    public function getLastExecutedTask(): ?TaskInterface;

    /**
     * Return the runners available in the worker.
     */
    public function getRunners(): RunnerRegistryInterface;

    /**
     * Return the configuration of the worker currently used.
     */
    public function getConfiguration(): WorkerConfiguration;
}
