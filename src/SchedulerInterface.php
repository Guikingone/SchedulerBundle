<?php

declare(strict_types=1);

namespace SchedulerBundle;

use DateTimeZone;
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
     */
    public function yieldTask(string $name, bool $async = false): void;

    /**
     * Update a specific task, the name should NOT be changed, every metadata can.
     */
    public function update(string $taskName, TaskInterface $task): void;

    /**
     * Pause a specific task, when paused, a task cannot be executed by the worker (but it can be sent to it).
     */
    public function pause(string $taskName, bool $async = false): void;

    /**
     * Re-enable a specific task (if disabled or paused), once resumed, the task can be executed.
     */
    public function resume(string $taskName): void;

    /**
     * Allow to retrieve every due tasks, the logic used to build the TaskList is own to the scheduler.
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function getDueTasks(): TaskListInterface;

    /**
     * Return the timezone used by the actual scheduler, each scheduler can use a different timezone.
     */
    public function getTimezone(): DateTimeZone;

    /**
     * Return every tasks scheduled.
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function getTasks(): TaskListInterface;

    /**
     * Remove every tasks except the ones that use the '@reboot' expression.
     *
     * The "reboot" tasks are re-scheduled and MUST be executed as soon as possible.
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function reboot(): void;
}
