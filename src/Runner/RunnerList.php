<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use Closure;
use function array_filter;
use function array_walk;
use function count;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RunnerList implements RunnerListInterface
{
    /**
     * @var RunnerInterface[]
     */
    private iterable $runners;

    /**
     * @param RunnerInterface[] $runners
     */
    public function __construct(iterable $runners)
    {
        $this->runners = $runners;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $func): RunnerListInterface
    {
        return new self(array_filter($this->runners, $func, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): RunnerListInterface
    {
        array_walk($this->runners, $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->runners);
    }
}
