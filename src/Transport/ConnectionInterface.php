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

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
interface ConnectionInterface
{
    public function create(TaskInterface $task): void;

    /**
     * @return TaskListInterface<string|int,TaskInterface>
     */
    public function list(): TaskListInterface;

    public function get(string $taskName): TaskInterface;

    public function update(string $taskName, TaskInterface $updatedTask): void;

    public function pause(string $taskName): void;

    public function resume(string $taskName): void;

    public function delete(string $taskName): void;

    public function empty(): void;
}
