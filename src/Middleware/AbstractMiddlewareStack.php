<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Closure;
use function array_filter;
use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractMiddlewareStack implements MiddlewareStackInterface
{
    protected iterable $stack;

    /**
     * @param iterable|PreExecutionMiddlewareInterface[]|PostExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|PostSchedulingMiddlewareInterface[] $stack
     */
    public function __construct(iterable $stack = [])
    {
        $this->stack = $stack;
    }

    protected function getPreSchedulingMiddleware(): array
    {
        return array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PreSchedulingMiddlewareInterface);
    }

    protected function getPostSchedulingMiddleware(): array
    {
        return array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PostSchedulingMiddlewareInterface);
    }

    protected function getPreExecutionMiddleware(): array
    {
        return array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PreExecutionMiddlewareInterface);
    }

    protected function getPostExecutionMiddleware(): array
    {
        return array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PostExecutionMiddlewareInterface);
    }

    protected function runMiddleware(array $middlewareList, Closure $func): void
    {
        array_walk($middlewareList, $func);
    }
}
