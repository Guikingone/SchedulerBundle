<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use function array_chunk;
use function array_filter;

use const ARRAY_FILTER_USE_BOTH;

use function array_key_exists;
use function array_key_last;
use function array_map;

use function array_values;
use function array_walk;

use ArrayIterator;
use Closure;

use function count;
use function gettype;
use function in_array;
use function is_string;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;

use function sprintf;

use Throwable;
use Traversable;

use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskList implements TaskListInterface
{
    /**
     * @var TaskInterface[]
     */
    private array $tasks = [];

    /**
     * @param TaskInterface[] $tasks
     */
    public function __construct(array $tasks = [])
    {
        foreach ($tasks as $task) {
            $this->add(task: $task);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function add(TaskInterface ...$task): void
    {
        if ([] === $task) {
            return;
        }

        array_walk(array: $task, callback: function (TaskInterface $task): void {
            $this->tasks[$task->getName()] = $task;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $taskName): bool
    {
        return array_key_exists(key: $taskName, array: $this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName, bool $lazy = false): TaskInterface|LazyTask
    {
        if ($lazy) {
            return new LazyTask(name: $taskName, sourceTaskClosure: Closure::bind(closure: fn (): TaskInterface => $this->get(taskName: $taskName), newThis: $this));
        }

        $task = $this->tasks[$taskName] ?? null;
        if (!$task instanceof TaskInterface) {
            throw new InvalidArgumentException(message: sprintf('The task "%s" does not exist or is invalid', $taskName));
        }

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(array $names): TaskListInterface|LazyTaskList
    {
        $filteredTasks = $this->filter(filter: static fn (TaskInterface $task): bool => in_array(needle: $task->getName(), haystack: $names, strict: true));

        return new TaskList(tasks: $filteredTasks->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $filter): TaskListInterface|LazyTaskList
    {
        return new TaskList(tasks: array_filter(array: $this->tasks, callback: $filter, mode: ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $taskName): void
    {
        if (!$this->has(taskName: $taskName)) {
            return;
        }

        unset($this->tasks[$taskName]);
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): TaskListInterface|LazyTaskList
    {
        array_walk(array: $this->tasks, callback: $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func, bool $keepKeys = true): array
    {
        $results = array_map(callback: $func, array: $this->tasks);

        return $keepKeys ? $results : array_values(array: $results);
    }

    /**
     * {@inheritdoc}
     */
    public function last(): TaskInterface
    {
        $lastIndex = array_key_last(array: $this->tasks);
        if (null === $lastIndex) {
            throw new RuntimeException(message: 'The current list is empty');
        }

        return $this->tasks[$lastIndex];
    }

    /**
     * {@inheritdoc}
     */
    public function uasort(Closure $func): TaskListInterface|LazyTaskList
    {
        uasort(array: $this->tasks, callback: $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function chunk(int $size, bool $preserveKeys = false): array
    {
        try {
            $chunks = array_chunk(array: $this->tasks, length: $size, preserve_keys: $preserveKeys);
        } catch (Throwable) {
            throw new InvalidArgumentException(message: sprintf('The given size "%d" cannot be used to split the list', $size));
        }

        return $chunks;
    }

    /**
     * {@inheritdoc}
     */
    public function slice(string ...$tasks): TaskListInterface|LazyTaskList
    {
        $toRetrieveTasks = $this->findByName(names: $tasks);
        if (0 === $toRetrieveTasks->count()) {
            throw new RuntimeException(message:'The tasks cannot be found');
        }

        return $toRetrieveTasks->walk(func: function (TaskInterface $task): void {
            $this->remove(taskName: $task->getName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $keepKeys = true): array
    {
        return $keepKeys ? $this->tasks : array_values(array: $this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        if (!is_string(value: $offset)) {
            throw new InvalidArgumentException(message: sprintf('The offset must be a string, received "%s"', gettype($offset)));
        }

        return $this->has(taskName: $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset): ?TaskInterface
    {
        if (!is_string(value: $offset)) {
            throw new InvalidArgumentException(message: sprintf('The offset must be a string, received "%s"', gettype($offset)));
        }

        return $this->get(taskName: $offset);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|null|mixed   $offset The name of the task, if null is passed and $value isn't, {@see TaskListInterface::add()} is called
     * @param TaskInterface|mixed $value A TaskInstance instance or mixed (will trigger an exception)
     *
     * @throws Throwable If the $value is not a {@see TaskInterface}
     */
    public function offsetSet($offset, $value): void
    {
        if (!$value instanceof TaskInterface) {
            throw new InvalidArgumentException(message: sprintf('A task must be given, received "%s"', gettype($value)));
        }

        null === $offset ? $this->add(task: $value) : $this->tasks[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        if (!is_string(value: $offset)) {
            throw new InvalidArgumentException(message: sprintf('The offset must be a string, received "%s"', gettype($offset)));
        }

        $this->remove(taskName: $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count(value: $this->tasks);
    }

    /**
     * @return ArrayIterator<int|string, TaskInterface>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array: $this->tasks);
    }
}
