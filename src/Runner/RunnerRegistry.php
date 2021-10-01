<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use Closure;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Task\TaskInterface;
use function array_filter;
use function current;
use function count;
use function is_array;
use function iterator_to_array;

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
        $this->runners = is_array($runners) ? $runners : iterator_to_array($runners);
    }

    /**
     * {@inheritdoc}
     */
    public function find(TaskInterface $task): RunnerInterface
    {
        $list = $this->filter(static fn (RunnerInterface $runner): bool => $runner->support($task));
        if (0 === $list->count()) {
            throw new InvalidArgumentException('No runner found for this task');
        }

        if (1 < $list->count()) {
            throw new InvalidArgumentException('More than one runner found, consider improving the task and/or the runner(s)');
        }

        return $list->current();
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $func): RunnerRegistryInterface
    {
        return new self(array_filter($this->runners, $func, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritdoc}
     */
    public function current(): RunnerInterface
    {
        $currentRunner = current($this->runners);
        if (false === $currentRunner) {
            throw new RuntimeException('The current runner cannot be found');
        }

        return $currentRunner;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->runners);
    }
}
