<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use ArrayAccess;
use Closure;
use Countable;
use IteratorAggregate;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TaskListInterface extends Countable, ArrayAccess, IteratorAggregate
{
    /**
     * Add a new task|a set of @param TaskInterface ...$task in the list, by default, the name of the task is used as the key.
     */
    public function add(TaskInterface ...$task): void;

    /**
     * Return if the task exist in the list using its @param string $taskName.
     */
    public function has(string $taskName): bool;

    /**
     * Return the desired {@see TaskInterface} if found using its @param string $taskName, otherwise, null.
     */
    public function get(string $taskName): ?TaskInterface;

    /**
     * @return TaskListInterface<string|int, TaskInterface> which contain the desired tasks using the names.
     *
     * @param array<int, string> $names
     */
    public function findByName(array $names): self;

    /**
     * Allow to filter the list using a custom filter, the @param Closure $filter receive the task name and the TaskInterface object (in this order).
     *
     * @return TaskListInterface<string, TaskInterface>
     */
    public function filter(Closure $filter): self;

    /**
     * Remove the task in the actual list if the @param string $taskName is a valid one.
     */
    public function remove(string $taskName): void;

    /**
     * Return the current list after applying the @param Closure $func to each tasks
     */
    public function walk(Closure $func): self;

    /**
     * Return the list as an array (using tasks name's as keys), if @param bool $keepKeys is false, the array is returned with indexed keys.
     *
     * @return array<string|int, TaskInterface>
     */
    public function toArray(bool $keepKeys = true): array;
}
