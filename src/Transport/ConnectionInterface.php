<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ConnectionInterface
{
    public function create(TaskInterface $task): void;

    /**
     * @return TaskListInterface<string, TaskInterface>
     */
    public function list(): TaskListInterface;

    public function get(string $taskTaskName): TaskInterface;

    public function update(string $taskName, TaskInterface $updatedTask): void;

    public function pause(string $taskName): void;

    public function resume(string $taskName): void;

    public function delete(string $taskTaskName): void;

    public function empty(): void;
}
