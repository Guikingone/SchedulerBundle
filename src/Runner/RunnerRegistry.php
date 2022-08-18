<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use function array_filter;

use Closure;

use function count;
use function current;

use function is_array;
use function iterator_to_array;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RunnerRegistry implements RunnerRegistryInterface
{
    /**
     * @var RunnerInterface[]
     */
    private array $runners;

    /**
     * @param RunnerInterface[] $runners
     */
    public function __construct(iterable $runners)
    {
        $this->runners = is_array(value: $runners) ? $runners : iterator_to_array(iterator: $runners);
    }

    /**
     * {@inheritdoc}
     */
    public function find(TaskInterface $task): RunnerInterface
    {
        $list = $this->filter(func: static fn (RunnerInterface $runner): bool => $runner->support(task: $task));
        if (0 === $list->count()) {
            throw new InvalidArgumentException(message: 'No runner found for this task');
        }

        if (1 < $list->count()) {
            throw new InvalidArgumentException(message: 'More than one runner found, consider improving the task and/or the runner(s)');
        }

        return $list->current();
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $func): RunnerRegistryInterface
    {
        return new self(runners: array_filter(array: $this->runners, callback:  $func, mode: ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritdoc}
     */
    public function current(): RunnerInterface
    {
        $currentRunner = current(array: $this->runners);
        if (false === $currentRunner) {
            throw new RuntimeException(message: 'The current runner cannot be found');
        }

        return $currentRunner;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count(value: $this->runners);
    }
}
