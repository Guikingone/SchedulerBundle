<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use ArrayIterator;
use Closure;
use function array_filter;
use function array_walk;
use function count;
use function is_array;
use function iterator_to_array;
use function uasort;
use const ARRAY_FILTER_USE_BOTH;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MiddlewareRegistry implements MiddlewareRegistryInterface
{
    /**
     * @var PostExecutionMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|PreExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[]|RequiredMiddlewareInterface[]
     */
    private array $middlewareList;

    /**
     * @param iterable|PostExecutionMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|PreExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[]|RequiredMiddlewareInterface[] $middlewareList
     */
    public function __construct(iterable $middlewareList)
    {
        $this->middlewareList = is_array($middlewareList) ? $middlewareList : iterator_to_array($middlewareList, true);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $func): MiddlewareRegistryInterface
    {
        return new self(array_filter($this->middlewareList, $func, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): MiddlewareRegistryInterface
    {
        array_walk($this->middlewareList, $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function uasort(Closure $func): MiddlewareRegistryInterface
    {
        uasort($this->middlewareList, $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->middlewareList;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->middlewareList);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->middlewareList);
    }
}
