<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Closure;
use function array_filter;
use function array_replace;
use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractMiddlewareStack implements MiddlewareStackInterface
{
    /**
     * @var iterable|PostExecutionMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|PreExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[]
     */
    protected iterable $stack;

    /**
     * @param iterable|PreExecutionMiddlewareInterface[]|PostExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[] $stack
     */
    public function __construct(iterable $stack = [])
    {
        $this->stack = $stack;
    }

    /**
     * @return PreSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[]
     */
    protected function getPreSchedulingMiddleware(): array
    {
        $list = array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PreSchedulingMiddlewareInterface);

        return $this->orderMiddleware($list);
    }

    /**
     * @return PostSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[]
     */
    protected function getPostSchedulingMiddleware(): array
    {
        $list = array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PostSchedulingMiddlewareInterface);

        return $this->orderMiddleware($list);
    }

    /**
     * @return PreExecutionMiddlewareInterface[]|OrderedMiddlewareInterface[]
     */
    protected function getPreExecutionMiddleware(): array
    {
        $list = array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PreExecutionMiddlewareInterface);

        return $this->orderMiddleware($list);
    }

    /**
     * @return PostExecutionMiddlewareInterface[]|OrderedMiddlewareInterface[]
     */
    protected function getPostExecutionMiddleware(): array
    {
        $list = array_filter($this->stack, fn ($middleware): bool => $middleware instanceof PostExecutionMiddlewareInterface);

        return $this->orderMiddleware($list);
    }

    /**
     * @param PostExecutionMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|PreExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[] $middlewareList
     * @param Closure                                                                                                                                                                  $func
     */
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
