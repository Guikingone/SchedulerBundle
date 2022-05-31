<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use ArrayAccess;
use Closure;
use Countable;
use IteratorAggregate;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @extends ArrayAccess<int|string, TaskInterface>
 * @extends IteratorAggregate<int|string, TaskInterface>
 */
interface TaskListInterface extends ArrayAccess, Countable, IteratorAggregate
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
     * Return the desired {@see TaskInterface} using @param string $taskName.
     *
     * Can return a {@see LazyTask} if @param bool $lazy is used and if the task can be found.
     *
     * @throws InvalidArgumentException If the task cannot be found or is not an instance of {@see TaskInterface}.
     */
    public function get(string $taskName, bool $lazy = false): TaskInterface|LazyTask;

    /**
     * @param array<int|string, string> $names
     *
     * @return TaskListInterface<string|int, TaskInterface>|LazyTaskList<string|int, TaskInterface>
     */
    public function findByName(array $names): TaskListInterface|LazyTaskList;

    /**
     * Allow to filter the list using a custom filter, the @param Closure $filter receive the task name and the TaskInterface object (in this order).
     *
     * @return TaskListInterface<string|int, TaskInterface>|LazyTaskList<string|int, TaskInterface>
     */
    public function filter(Closure $filter): TaskListInterface|LazyTaskList;

    /**
     * Remove the task in the actual list if the @param string $taskName is a valid one.
     */
    public function remove(string $taskName): void;

    /**
     * Return the current list after applying the @param Closure $func to each task.
     *
     * @return TaskListInterface<string|int, TaskInterface>|LazyTaskList<string|int, TaskInterface>
     */
    public function walk(Closure $func): TaskListInterface|LazyTaskList;

    /**
     * Return an array containing the results of applying @param Closure $func to each tasks
     *
     * Depending on @param bool $keepKeys The final array can be indexed using numeric keys.
     *
     * @return array<int|string, mixed>
     */
    public function map(Closure $func, bool $keepKeys = true): array;

    /**
     * Return the last task of the list.
     *
     * @throws RuntimeException If the list is empty or if the last task cannot be found.
     */
    public function last(): TaskInterface;

    /**
     * Allow to sort the tasks using @param Closure $func, the current list is returned with the sorted tasks.
     *
     * @return TaskListInterface<string|int, TaskInterface>|LazyTaskList<string|int, TaskInterface>
     */
    public function uasort(Closure $func): TaskListInterface|LazyTaskList;

    /**
     * For more information, see @link https://php.net/manual/en/function.array-chunk.php
     *
     * @param bool $preserveKeys If used, the task name as keys are preserved.
     * @param int<1, max> $size  Define the size of each chunk, must be equal to or greater than 1.
     *
     * @return array<int, array<string|int, TaskInterface>>
     */
    public function chunk(int $size, bool $preserveKeys = false): array;

    /**
     * Remove and return a list of tasks from the current one.
     *
     * For an equivalent, see @link https://php.net/manual/en/function.array-slice.php
     *
     * @return TaskListInterface<string|int, TaskInterface>|LazyTaskList<string|int, TaskInterface>
     *
     * @throws RuntimeException If the tasks cannot be found.
     */
    public function slice(string ...$tasks): TaskListInterface|LazyTaskList;

    /**
     * Return the list as an array (using tasks name's as keys), if @param bool $keepKeys is false, the array is returned with indexed keys.
     *
     * @return array<string|int, TaskInterface>
     */
    public function toArray(bool $keepKeys = true): array;
}
