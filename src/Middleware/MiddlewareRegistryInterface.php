<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Closure;
use Countable;
use IteratorAggregate;
use function uasort;
use const ARRAY_FILTER_USE_BOTH;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface MiddlewareRegistryInterface extends Countable, IteratorAggregate
{
    /**
     * Filter the current middleware list using @param Closure $func.
     *
     * The filter receive both key and middleware object {@see ARRAY_FILTER_USE_BOTH}, the given filter SHOULD return a bool.
     *
     * The filter is done in an atomic approach, a new {@see MiddlewareRegistryInterface} is returned.
     */
    public function filter(Closure $func): MiddlewareRegistryInterface;

    /**
     * Apply the @param Closure $func to every middleware in the list.
     *
     * The filter is done in an atomic approach, a new {@see MiddlewareRegistryInterface} is returned.
     */
    public function walk(Closure $func): MiddlewareRegistryInterface;

    /**
     * Allow to sort the current middleware list using @param Closure $func.
     *
     * The index association is maintained, {@see uasort()}
     *
     * The filter is done in an atomic approach, a new {@see MiddlewareRegistryInterface} is returned.
     */
    public function uasort(Closure $func): MiddlewareRegistryInterface;

    /**
     * @return array<PostExecutionMiddlewareInterface|PreExecutionMiddlewareInterface|PreSchedulingMiddlewareInterface|RequiredMiddlewareInterface>
     */
    public function toArray(): array;
}
