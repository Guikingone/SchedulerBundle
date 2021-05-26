<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Closure;
use Traversable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTaskList implements TaskListInterface
{
    private ?TaskListInterface $sourceList;
    private ?TaskListInterface $list;
    private bool $initialized = false;

    public function __construct(TaskListInterface $list)
    {
        $this->sourceList = $list;
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

        return $this->list->has($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): ?TaskInterface
    {
        $this->initialize();

        return $this->list->get($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(array $names): TaskListInterface
    {
        $this->initialize();

        return $this->list->findByName($names);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $filter): TaskListInterface
    {
        $this->initialize();

        return $this->list->filter($filter);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $taskName): void
    {
        $this->initialize();

        $this->list->remove($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): TaskListInterface
    {
        if ($this->initialized) {
            return $this->list->walk($func);
        }

        return $this->sourceList->walk($func);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $keepKeys = true): array
    {
        $this->initialize();

        return $this->list->toArray($keepKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        $this->initialize();

        return $this->list->offsetExists($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset): ?TaskInterface
    {
        if ($this->initialized) {
            return $this->list->offsetGet($offset);
        }

        return $this->sourceList->offsetGet($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value): void
    {
        if ($this->initialized) {
            $this->list->offsetSet($offset, $value);

            return;
        }

        $this->sourceList->offsetSet($offset, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        $this->initialize();

        $this->list->offsetUnset($offset);
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
    public function getIterator(): Traversable
    {
        $this->initialize();

        return $this->list->getIterator();
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->list = new TaskList($this->sourceList->toArray());
        $this->initialized = true;
    }
}
