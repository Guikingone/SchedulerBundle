<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Closure;
use SchedulerBundle\Exception\RuntimeException;
use Symfony\Component\Lock\Key;
use Traversable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LockedTaskList implements TaskListInterface
{
    public function __construct(
        private Key $key,
        private TaskListInterface|LazyTaskList $sourceList
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function add(TaskInterface ...$task): void
    {
        $this->checkIfKeyIsExpired();

        $this->sourceList->add(...$task);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $taskName): bool
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->has(taskName: $taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName, bool $lazy = false): TaskInterface|LazyTask
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->get(taskName: $taskName, lazy: $lazy);
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(array $names): TaskListInterface|LazyTaskList
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->findByName(names: $names);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $filter): TaskListInterface|LazyTaskList
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->filter(filter: $filter);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $taskName): void
    {
        $this->checkIfKeyIsExpired();

        $this->sourceList->remove(taskName: $taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): TaskListInterface|LazyTaskList
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->walk(func: $func);
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func, bool $keepKeys = true): array
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->map(func: $func, keepKeys: $keepKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function last(): TaskInterface
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->last();
    }

    /**
     * {@inheritdoc}
     */
    public function uasort(Closure $func): TaskListInterface|LazyTaskList
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->uasort(func: $func);
    }

    /**
     * {@inheritdoc}
     */
    public function chunk(int $size, bool $preserveKeys = false): array
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->chunk(size: $size, preserveKeys: $preserveKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function slice(string ...$tasks): TaskListInterface|LazyTaskList
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->slice(...$tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $keepKeys = true): array
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->toArray(keepKeys: $keepKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->offsetExists(offset: $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet(mixed $offset): TaskInterface|LazyTask
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->offsetGet(offset: $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->checkIfKeyIsExpired();

        $this->sourceList->offsetSet(offset: $offset, value: $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->checkIfKeyIsExpired();

        $this->sourceList->offsetUnset(offset: $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        $this->checkIfKeyIsExpired();

        return $this->sourceList->getIterator();
    }

    public function getKey(): Key
    {
        return $this->key;
    }

    private function checkIfKeyIsExpired(): void
    {
        if ($this->key->isExpired()) {
            throw new RuntimeException(message: 'The current list is expired and cannot be used anymore.');
        }
    }
}
