<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Closure;
use function array_filter;
use function array_merge;
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
        $list = array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PreSchedulingMiddlewareInterface);

        return $this->orderMiddleware($list);
    }

    protected function getPostSchedulingMiddleware(): array
    {
        $list = array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PostSchedulingMiddlewareInterface);

        return $this->orderMiddleware($list);
    }

    protected function getPreExecutionMiddleware(): array
    {
        $list = array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PreExecutionMiddlewareInterface);

        return $this->orderMiddleware($list);
    }

    protected function getPostExecutionMiddleware(): array
    {
        $list = array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PostExecutionMiddlewareInterface);

        return $this->orderMiddleware($list);
    }

    protected function runMiddleware(array $middlewareList, Closure $func): void
    {
        array_walk($middlewareList, $func);
    }

    private function orderMiddleware(array $middlewareList): array
    {
        $orderedMiddleware = array_filter($this->stack, fn (object $middleware): bool => $middleware instanceof OrderedMiddlewareInterface);

        if ([] === $orderedMiddleware) {
            return $middlewareList;
        }

        uasort($orderedMiddleware, fn (OrderedMiddlewareInterface $middleware, OrderedMiddlewareInterface $nextMiddleware): int => $middleware->getPriority() < $nextMiddleware->getPriority() ? -1 : 1);

        return array_replace($middlewareList, $orderedMiddleware);
    }
}
