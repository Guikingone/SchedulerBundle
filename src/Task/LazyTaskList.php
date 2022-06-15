<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use ArrayIterator;
use Closure;
use SchedulerBundle\LazyInterface;
use Traversable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTaskList implements TaskListInterface, LazyInterface
{
    private TaskListInterface $list;
    private bool $initialized = false;

    public function __construct(private readonly TaskListInterface $sourceList)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function add(TaskInterface ...$task): void
    {
        $this->initialize();

        $this->list->add(...$task);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $taskName): bool
    {
        $this->initialize();

        return $this->list->has(taskName: $taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName, bool $lazy = false): TaskInterface|LazyTask
    {
        if ($this->initialized) {
            return $this->list->get(taskName: $taskName, lazy: $lazy);
        }

        return $this->sourceList->get(taskName: $taskName, lazy: $lazy);
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(array $names): TaskListInterface|LazyTaskList
    {
        $this->initialize();

        $self = new self(sourceList: $this->list->findByName(names: $names));
        $self->initialize();

        return $self;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $filter): TaskListInterface|LazyTaskList
    {
        $this->initialize();

        $self = new self(sourceList: $this->list->filter(filter: $filter));
        $self->initialize();

        return $self;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $taskName): void
    {
        $this->initialize();

        $this->list->remove(taskName: $taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): TaskListInterface|LazyTaskList
    {
        if ($this->initialized) {
            return $this->list->walk(func: $func);
        }

        return $this->sourceList->walk(func: $func);
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func, bool $keepKeys = true): array
    {
        if ($this->initialized) {
            return $this->list->map(func: $func, keepKeys: $keepKeys);
        }

        $results = $this->sourceList->map(func: $func, keepKeys: $keepKeys);

        $this->initialize();

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function last(): TaskInterface
    {
        $this->initialize();

        return $this->list->last();
    }

    /**
     * {@inheritdoc}
     */
    public function uasort(Closure $func): TaskListInterface|LazyTaskList
    {
        if ($this->initialized) {
            return $this->list->uasort(func: $func);
        }

        return $this->sourceList->uasort(func: $func);
    }

    /**
     * {@inheritdoc}
     */
    public function chunk(int $size, bool $preserveKeys = false): array
    {
        $this->initialize();

        return $this->list->chunk(size: $size, preserveKeys: $preserveKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function slice(string ...$tasks): TaskListInterface|LazyTaskList
    {
        $this->initialize();

        return $this->list->slice(...$tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $keepKeys = true): array
    {
        $this->initialize();

        return $this->list->toArray(keepKeys: $keepKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        $this->initialize();

        return $this->list->offsetExists(offset: $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset): ?TaskInterface
    {
        if ($this->initialized) {
            return $this->list->offsetGet(offset: $offset);
        }

        return $this->sourceList->offsetGet(offset: $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value): void
    {
        if ($this->initialized) {
            $this->list->offsetSet(offset: $offset, value: $value);

            return;
        }

        $this->sourceList->offsetSet(offset: $offset, value: $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        $this->initialize();

        $this->list->offsetUnset(offset: $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        $this->initialize();

        return $this->list->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): ArrayIterator|Traversable
    {
        $this->initialize();

        return $this->list->getIterator();
    }

    /**
     * {@inheritdoc}
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->list = new TaskList(tasks: $this->sourceList->toArray());
        $this->initialized = true;
    }
}
