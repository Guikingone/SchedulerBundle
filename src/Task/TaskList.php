<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskList implements TaskListInterface
{
    private $tasks = [];

    /**
     * @param TaskInterface[] $tasks
     *
     * @throws \Throwable {@see TaskList::add()}
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
    public function add(TaskInterface ...$tasks): void
    {
        if (!$tasks) {
            return;
        }

        foreach ($tasks as $task) {
            try {
                $this->tasks[$task->getName()] = $task;
            } catch (\Throwable $throwable) {
                $this->remove($task->getName());

                throw $throwable;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $taskName): bool
    {
        return isset($this->tasks[$taskName]);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): ?TaskInterface
    {
        return $this->tasks[$taskName] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(array $names): TaskListInterface
    {
        $tasks = [];

        foreach ($this->tasks as $task) {
            if (\in_array($task->getName(), $names, true)) {
                $tasks[] = $task;
            }
        }

        return new static($tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(\Closure $filter): TaskListInterface
    {
        return new static(array_filter($this->tasks, $filter, \ARRAY_FILTER_USE_BOTH));
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
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        if (!$value instanceof TaskInterface) {
            throw new \InvalidArgumentException('A task must be given, received %s', \gettype($value));
        }

        null === $offset ? $this->add($value) : $this->tasks[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return \count($this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $keepKeys = true): array
    {
        return $keepKeys ? $this->tasks : array_values($this->tasks);
    }
}
