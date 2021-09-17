<?php

declare(strict_types=1);

namespace SchedulerBundle;

use Closure;
use DateTimeZone;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\TransportInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SchedulerInterface
{
    /**
     * Schedule a specific {@see TaskInterface}, the storage of the task is up to the scheduler.
     */
    public function schedule(TaskInterface $task): void;

    /**
     * Un-schedule a specific task, once un-scheduled, the task is removed from the scheduler.
     */
    public function unschedule(string $taskName): void;

    /**
     * Dequeue the task {@param string $name} then re-schedule it.
     *
     * If the argument {@param bool $async} is used, the action is done via the message bus (if injected).
     *
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function yieldTask(string $name, bool $async = false): void;

    /**
     * Determine if a task|set of due tasks that entered the runnable state should preempt the @param string $taskToPreempt.
     *
     * The decision is based on @param Closure $filter, if the closure returns true, the task(s) can preempt.
     *
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     *
     * {@internal The preemption is done in an "atomic approach", the currently running worker is paused,
     *            the remaining tasks are retrieved then the worker is forked, once forked,
     *            the new worker execute the preempting tasks then stop.
     *            Once the new worker stopped, the "old" worker is restarted then the remaining tasks are executed.}
     */
    public function preempt(string $taskToPreempt, Closure $filter): void;

    /**
     * Update a specific task, the name should NOT be changed, every metadata can.
     *
     * If @param bool $async is used, the action is performed asynchronously.
     */
    public function update(string $taskName, TaskInterface $task, bool $async = false): void;

    /**
     * Pause a specific task, when paused, a task cannot be executed by the worker (but it can be sent to it).
     */
    public function pause(string $taskName, bool $async = false): void;

    /**
     * Re-enable a specific task (if disabled or paused), once resumed, the task can be executed.
     */
    public function resume(string $taskName): void;

    /**
     * Return every tasks scheduled.
     *
     * Can return a {@see LazyTaskList} if @param bool $lazy is used
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function getTasks(bool $lazy = false): TaskListInterface;

    /**
     * Allow to retrieve every due tasks, the logic used to build the TaskList is own to the scheduler.
     *
     * If the @param bool $lazy is used, the tasks are lazy-loaded.
     * If the @param bool $strict is used, the current date will assert that the seconds are equals to '00'.
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function getDueTasks(bool $lazy = false, bool $strict = false): TaskListInterface;

    /**
     * Return the next task that must be executed (based on {@see SchedulerInterface::getDueTasks()})
     *
     * Can lazy-load the task list if @param bool $lazy is used, the task will be returned via a {@see LazyTask}
     *
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function next(bool $lazy = false): TaskInterface;

    /**
     * Remove every task except the ones that use the '@reboot' expression.
     *
     * The "reboot" tasks are re-scheduled and MUST be executed as soon as possible.
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function reboot(): void;

    /**
     * Return the timezone used by the actual scheduler, each scheduler can use a different timezone.
     */
    public function getTimezone(): DateTimeZone;
}
