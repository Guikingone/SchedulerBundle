<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TransportInterface
{
    /**
     * Return a stored task, if the task does not exist, an {@see InvalidArgumentException} MUST be thrown.
     *
     * @param string $name
     *
     * @return TaskInterface
     */
    public function get(string $name): TaskInterface;

    /**
     * Return all the tasks as a {@see TaskListInterface}, the tasks name are used as keys.
     *
     * @return TaskListInterface<string, TaskInterface>
     */
    public function list(): TaskListInterface;

    /**
     * Add the task into the transport list, if the task name already exist, the new task is not added.
     *
     * @param TaskInterface $task
     */
    public function create(TaskInterface $task): void;

    /**
     * Update an existing task using the $updatedTask payload, if the task does not exist, it should be created.
     *
     * The recommended approach is to call {@see TransportInterface::get()} first to retrieve the task.
     *
     * @param string        $name
     * @param TaskInterface $updatedTask
     */
    public function update(string $name, TaskInterface $updatedTask): void;

    /**
     * Delete a task, if this task does not exist, an {@see InvalidArgumentException} COULD be thrown.
     *
     * @param string $name
     */
    public function delete(string $name): void;

    /**
     * Allow to pause a task, if the task does not exist, a {@see InvalidArgumentException} MUST be thrown.
     *
     * If the task exist but it's already paused, a {@see LogicException} must be thrown.
     *
     * @param string $name
     */
    public function pause(string $name): void;

    /**
     * Allow to resume a task, if the task does not exist, a {@see InvalidArgumentException} MUST be thrown.
     *
     * If the task exist but it's already resumed, a {@see LogicException} must be thrown.
     *
     * @param string $name
     */
    public function resume(string $name): void;

    /**
     * Remove all the tasks from the current transport.
     */
    public function clear(): void;

    /**
     * @return array<string, int|string|bool|array|null>
     */
    public function getOptions(): array;
}
