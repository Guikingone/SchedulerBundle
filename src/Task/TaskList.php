<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use ArrayIterator;
use Closure;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use Throwable;
use function array_chunk;
use function array_filter;
use function array_key_exists;
use function array_key_last;
use function array_values;
use function array_walk;
use function array_map;
use function uasort;
use function count;
use function gettype;
use function in_array;
use function sprintf;
use const ARRAY_FILTER_USE_BOTH;

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
            $this->add($task);
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

        array_walk($task, function (TaskInterface $task): void {
            $this->tasks[$task->getName()] = $task;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $taskName): bool
    {
        return array_key_exists($taskName, $this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName, bool $lazy = false): TaskInterface
    {
        if ($lazy) {
            return new LazyTask($taskName, Closure::bind(fn (): TaskInterface => $this->get($taskName), $this));
        }

        $task = $this->tasks[$taskName] ?? null;
        if (!$task instanceof TaskInterface) {
            throw new InvalidArgumentException(sprintf('The task "%s" does not exist or is invalid', $taskName));
        }

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(array $names): TaskListInterface
    {
        $filteredTasks = $this->filter(static fn (TaskInterface $task): bool => in_array($task->getName(), $names, true));

        return new TaskList($filteredTasks->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $filter): TaskListInterface
    {
        return new TaskList(array_filter($this->tasks, $filter, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $taskName): void
    {
        if (!$this->has($taskName)) {
            return;
        }

        unset($this->tasks[$taskName]);
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): TaskListInterface
    {
        array_walk($this->tasks, $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func, bool $keepKeys = true): array
    {
        $results = array_map($func, $this->tasks);

        return $keepKeys ? $results : array_values($results);
    }

    /**
     * {@inheritdoc}
     */
    public function last(): TaskInterface
    {
        $lastIndex = array_key_last($this->tasks);
        if (null === $lastIndex) {
            throw new RuntimeException('The current list is empty');
        }

        return $this->tasks[$lastIndex];
    }

    /**
     * {@inheritdoc}
     */
    public function uasort(Closure $func): TaskListInterface
    {
        uasort($this->tasks, $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function chunk(int $size, bool $preserveKeys = false): array
    {
        try {
            $chunks = array_chunk($this->tasks, $size, $preserveKeys);
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException(sprintf('The given size "%d" cannot be used to split the list', $size));
        }

        return $chunks;
    }

    /**
     * {@inheritdoc}
     */
    public function slice(string ...$tasks): TaskListInterface
    {
        $toRetrieveTasks = $this->findByName($tasks);
        if (0 === $toRetrieveTasks->count()) {
            throw new RuntimeException('The tasks cannot be found');
        }

        return $toRetrieveTasks->walk(function (TaskInterface $task): void {
            $this->remove($task->getName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $keepKeys = true): array
    {
        return $keepKeys ? $this->tasks : array_values($this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset): ?TaskInterface
    {
        return $this->get($offset);
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
            throw new InvalidArgumentException(sprintf('A task must be given, received "%s"', gettype($value)));
        }

        null === $offset ? $this->add($value) : $this->tasks[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->tasks);
    }

    /**
     * @return ArrayIterator<int|string, TaskInterface>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->tasks);
    }
}
