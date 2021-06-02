<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TransportInterface
{
    /**
     * Return a stored task, if the task does not exist, an {@see InvalidArgumentException} MUST be thrown.
     *
     * Can return a {@see LazyTask} if @param bool $lazy is used.
     */
    public function get(string $name, bool $lazy = false): TaskInterface;

    /**
     * Can return a {@see LazyTaskList} if @param bool $lazy is used
     *
     * @throws Throwable
     */
    public function list(bool $lazy = false): TaskListInterface;

    /**
     * Add the task into the transport list, if the task name already exist, the new task is not added.
     */
    public function create(TaskInterface $task): void;

    /**
     * Update an existing task using the $updatedTask payload, if the task does not exist, it should be created.
     *
     * The recommended approach is to call {@see TransportInterface::get()} first to retrieve the task.
     */
    public function update(string $name, TaskInterface $updatedTask): void;

    /**
     * Delete a task, if this task does not exist, an {@see InvalidArgumentException} COULD be thrown.
     */
    public function delete(string $name): void;

    /**
     * Allow to pause a task, if the task does not exist, a {@see InvalidArgumentException} MUST be thrown.
     *
     * If the task exist but it's already paused, a {@see LogicException} must be thrown.
     */
    public function pause(string $name): void;

    /**
     * Allow to resume a task, if the task does not exist, a {@see InvalidArgumentException} MUST be thrown.
     *
     * If the task exist but it's already resumed, a {@see LogicException} must be thrown.
     */
    public function resume(string $name): void;

    /**
     * Remove all the tasks from the current transport.
     */
    public function clear(): void;

    /**
     * @return array<string, mixed|int|float|string|bool|array|null>
     */
    public function getOptions(): array;
}
