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
use function next;

use const ARRAY_FILTER_USE_BOTH;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MiddlewareRegistry implements MiddlewareRegistryInterface
{
    /**
     * @var array<int, mixed>
     */
    private array $middlewareList;

    /**
     * @param iterable|PostExecutionMiddlewareInterface[]|PreExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|RequiredMiddlewareInterface[]|OrderedMiddlewareInterface[] $middlewareList
     */
    public function __construct(iterable $middlewareList)
    {
        $this->middlewareList = is_array(value: $middlewareList)
            ? $middlewareList
            : iterator_to_array(iterator: $middlewareList, preserve_keys: true)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $func): MiddlewareRegistryInterface
    {
        return new self(middlewareList: array_filter(array: $this->middlewareList, callback: $func, mode: ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): MiddlewareRegistryInterface
    {
        array_walk(array: $this->middlewareList, callback: $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function uasort(Closure $func): MiddlewareRegistryInterface
    {
        uasort(array: $this->middlewareList, callback: $func);

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
    public function next(): void
    {
        next(array: $this->middlewareList);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count(value: $this->middlewareList);
    }

    /**
     * @return ArrayIterator<int, mixed>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator(array: $this->middlewareList);
    }
}
