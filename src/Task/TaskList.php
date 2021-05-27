<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use ArrayIterator;
use Closure;
use SchedulerBundle\Exception\InvalidArgumentException;
use Throwable;
use function array_filter;
use function array_key_exists;
use function array_values;
use function array_walk;
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
        if ($task === []) {
            return;
        }

        array_walk($task, fn (TaskInterface $task) => $this->tasks[$task->getName()] = $task);
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
    public function get(string $taskName, bool $lazy = false): ?TaskInterface
    {
        if ($lazy) {
            return new LazyTask($taskName, Closure::bind(fn (): ?TaskInterface => $this->get($taskName), $this));
        }

        return $this->tasks[$taskName] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(array $names): TaskList
    {
        $filteredTasks = $this->filter(fn (TaskInterface $task): bool => in_array($task->getName(), $names, true));

        return new TaskList($filteredTasks->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $filter): TaskList
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
     * {@inheritdoc}
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->tasks);
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
    public function toArray(bool $keepKeys = true): array
    {
        return $keepKeys ? $this->tasks : array_values($this->tasks);
    }
}
