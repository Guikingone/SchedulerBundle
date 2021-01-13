<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
interface TransportInterface
{
    public function get(string $name): TaskInterface;

    /**
     * @return TaskListInterface<string|int,TaskInterface>
     */
    public function list(): TaskListInterface;

    /**
     * Add the task into the transport list, if the task name already exist, the new task is not added.
     */
    public function create(TaskInterface $task): void;

    /**
     * Update an existing task using the $updatedTask payload, if the task does not exist, it should be created.
     */
    public function update(string $name, TaskInterface $updatedTask): void;

    public function delete(string $name): void;

    /**
     * Allow to pause a task, if the task does not exist, a {@see InvalidArgumentException} must be thrown.
     *
     * If the task exist but it's already paused, a {@see LogicException} must be thrown.
     */
    public function pause(string $name): void;

    public function resume(string $name): void;

    public function clear(): void;

    /**
     * @return array<string,int|string|bool|array>
     */
    public function getOptions(): array;
}
