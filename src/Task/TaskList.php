<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use ArrayIterator;
use Closure;
use InvalidArgumentException;
use Throwable;
use function array_filter;
use function array_map;
use function array_values;
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
    private $tasks = [];

    /**
     * @param TaskInterface[] $tasks
     *
     * @throws Throwable {@see TaskList::add()}
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
        if ($tasks === []) {
            return;
        }

        array_map(function (TaskInterface $task): void {
            try {
                $this->tasks[$task->getName()] = $task;
            } catch (Throwable $throwable) {
                $this->remove($task->getName());

                throw $throwable;
            }
        }, $tasks);
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
        return new self(array_filter($this->tasks, function (TaskInterface $task) use ($names): bool {
            return in_array($task->getName(), $names, true);
        }));
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $filter): TaskListInterface
    {
        return new self(array_filter($this->tasks, $filter, ARRAY_FILTER_USE_BOTH));
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
     */
    public function offsetSet($offset, $value): void
    {
        if (!$value instanceof TaskInterface) {
            throw new InvalidArgumentException(sprintf('A task must be given, received %s', gettype($value)));
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
     * @return TaskInterface[]
     */
    public function toArray(bool $keepKeys = true): array
    {
        return $keepKeys ? $this->tasks : array_values($this->tasks);
    }
}
