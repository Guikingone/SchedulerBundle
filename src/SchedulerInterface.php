<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
interface SchedulerInterface
{
    /**
     * Schedule a specific task, the storage of the task is up to the scheduler.
     */
    public function schedule(TaskInterface $task): void;

    /**
     * Un-schedule a specific task, once un-scheduled, the task is removed from the scheduler.
     */
    public function unschedule(string $taskName): void;

    /**
     * Update a specific task, the name should NOT be changed, every metadata can.
     */
    public function update(string $taskName, TaskInterface $task): void;

    /**
     * Pause a specific task, when paused, a task cannot be executed by the worker (but it can be sent to it).
     */
    public function pause(string $taskName): void;

    /**
     * Re-enable a specific task (if disabled or paused), once resumed, the task can be executed.
     */
    public function resume(string $taskName): void;

    /**
     * Allow to retrieve every due tasks, the logic used to build the TaskList is own to the scheduler.
     *
     * @return TaskListInterface<string|int,TaskInterface>
     */
    public function getDueTasks(): TaskListInterface;

    /**
     * Return the timezone used by the actual scheduler, each scheduler can use a different timezone.
     */
    public function getTimezone(): \DateTimeZone;

    /**
     * Return every tasks scheduled.
     *
     * @return TaskListInterface<string|int,TaskInterface>
     */
    public function getTasks(): TaskListInterface;

    /**
     * Remove every tasks except the ones that use the '@reboot' expression.
     *
     * The "reboot" tasks are re-scheduled and MUST be executed as soon as possible.
     */
    public function reboot(): void;
}
