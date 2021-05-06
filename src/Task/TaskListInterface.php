<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use ArrayAccess;
use Closure;
use Countable;
use IteratorAggregate;
use SchedulerBundle\Exception\RuntimeException;

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
     *
     * Can return a {@see LazyTask} if @param bool $lazy is used and if the task can be found.
     */
    public function get(string $taskName, bool $lazy = false): ?TaskInterface;

    /**
     * @param array<int, string> $names
     */
    public function findByName(array $names): self;

    /**
     * Allow to filter the list using a custom filter, the @param Closure $filter receive the task name and the TaskInterface object (in this order).
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
     * Return an array containing the results of applying @param Closure $func to each tasks
     */
    public function map(Closure $func): array;

    /**
     * Return the last task of the list.
     *
     * @throws RuntimeException If the list is empty or if the last task cannot be found.
     */
    public function last(): TaskInterface;

    /**
     * Return the list as an array (using tasks name's as keys), if @param bool $keepKeys is false, the array is returned with indexed keys.
     *
     * @return array<string|int, TaskInterface>
     */
    public function toArray(bool $keepKeys = true): array;
}
