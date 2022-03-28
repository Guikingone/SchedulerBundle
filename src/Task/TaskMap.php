<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Closure;
use Ds\Map;
use Generator;
use Traversable;
use function array_walk;

final class TaskMap implements TaskListInterface
{
    private Map $map;

    public function __construct(array $tasks = [])
    {
        $this->map = new Map();

        $this->add(...$tasks);
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
            $this->map->put($task->getName(), $task);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $taskName): bool
    {
        return $this->map->hasKey($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName, bool $lazy = false): TaskInterface
    {
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(array $names): TaskListInterface
    {
        // TODO: Implement findByName() method.
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $filter): TaskListInterface
    {
        // TODO: Implement filter() method.
    }

    public function remove(string $taskName): void
    {
        $this->map->remove($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): TaskListInterface
    {
        // TODO: Implement walk() method.
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func, bool $keepKeys = true): array
    {
        $mappedMapValues = $this->map->map($func);

        return $keepKeys ? $mappedMapValues->values()->toArray() : $mappedMapValues->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function last(): TaskInterface
    {
        // TODO: Implement last() method.
    }

    /**
     * {@inheritdoc}
     */
    public function uasort(Closure $func): TaskListInterface
    {
    }

    /**
     * {@inheritdoc}
     */
    public function chunk(int $size, bool $preserveKeys = false): array
    {
        // TODO: Implement chunk() method.
    }

    /**
     * {@inheritdoc}
     */
    public function slice(string ...$tasks): TaskListInterface
    {
        // TODO: Implement slice() method.
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $keepKeys = true): array
    {
        // TODO: Implement toArray() method.
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet(mixed $offset): TaskInterface
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet(mixed $offset, mixed $value)
    {
        // TODO: Implement offsetSet() method.
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->map->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable|array|Generator
    {
        return $this->map->getIterator();
    }
}