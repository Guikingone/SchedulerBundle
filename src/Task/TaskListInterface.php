<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use ArrayAccess;
use Closure;
use Countable;
use IteratorAggregate;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TaskListInterface extends Countable, ArrayAccess, IteratorAggregate
{
    /**
     * Add a new task|a set of tasks in the list, by default, the name of the task is used as the key.
     *
     *
     * @throws Throwable if a task cannot be added|created in a local or remote transport,
     *                   the task is removed from the list and the exception thrown
     */
    public function add(TaskInterface ...$tasks): void;

    /**
     * Return if the task exist in the list using its name.
     *
     *
     */
    public function has(string $taskName): bool;

    /**
     * Return the desired task if found using its name, otherwise, null.
     *
     *
     */
    public function get(string $taskName): ?TaskInterface;

    /**
     * Return a new list which contain the desired tasks using the names.
     *
     * @param array<int, string> $names
     *
     * @return TaskListInterface<string, TaskInterface>
     */
    public function findByName(array $names): self;

    /**
     * Allow to filter the list using a custom filter, the $filter receive the task name and the TaskInterface object (in this order).
     *
     *
     * @return TaskListInterface<string, TaskInterface>
     */
    public function filter(Closure $filter): self;

    /**
     * Remove the task in the actual list if the name is a valid one.
     */
    public function remove(string $taskName): void;

    /**
     * Return the list as an array (using tasks name's as keys), if $keepKeys is false, the array is returned with indexed keys.
     *
     *
     * @return array<string|int, TaskInterface>
     */
    public function toArray(bool $keepKeys = true): array;
}
