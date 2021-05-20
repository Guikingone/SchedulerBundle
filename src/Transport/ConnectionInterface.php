<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ConnectionInterface
{
    /**
     * Create a new task using {@param TaskInterface $task}, the operation must occurs withing a transaction if possible.
     *
     * {@internal If a task already exist using the task name, the operation MUST be aborted without throwing an error}
     */
    public function create(TaskInterface $task): void;

    /**
     * {@internal The task retrieving approach is not described as every connection defines a specific solution}
     */
    public function list(): TaskListInterface;

    /**
     * Return a single task using its {@param string $taskName}.
     *
     * {@internal If possible (or supported by the connection), the task MUST be returned using a transaction or similar}
     */
    public function get(string $taskName): TaskInterface;

    /**
     * Update a task using its {@param string $taskName} and the new body supplied via {@param TaskInterface $updatedTask}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void;

    /**
     * Retrieve a task using {@param string $taskName} and set its state to {@see TaskInterface::PAUSED}.
     *
     * If the state cannot be set, a {@see TransportException} must be thrown.
     */
    public function pause(string $taskName): void;

    /**
     * Retrieve a task using {@param string $taskName} and set its state to {@see TaskInterface::ENABLED}.
     *
     * If the state cannot be set, a {@see TransportException} must be thrown.
     */
    public function resume(string $taskName): void;

    /**
     * Delete a task using {@param string $taskName}, the delete MUST occurs within a transaction if possible.
     */
    public function delete(string $taskName): void;

    /**
     * Remove every tasks stored via the connection, the operation MUST occurs within a transaction if possible.
     */
    public function empty(): void;
}
