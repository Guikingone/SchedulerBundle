<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Closure;
use SplObjectStorage;
use Throwable;
use function array_replace;
use function array_values;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractMiddlewareStack implements MiddlewareStackInterface
{
    private MiddlewareRegistryInterface $middlewareRegistry;
    private SplObjectStorage $executedMiddleware;

    /**
     * @param iterable|PreExecutionMiddlewareInterface[]|PostExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[] $stack
     */
    public function __construct(iterable $stack = [])
    {
        $this->middlewareRegistry = new MiddlewareRegistry($stack);
        $this->executedMiddleware = new SplObjectStorage();
    }

    protected function getPreSchedulingMiddleware(): MiddlewareRegistryInterface
    {
        return $this->orderMiddleware($this->middlewareRegistry->filter(static fn (object $middleware): bool => $middleware instanceof PreSchedulingMiddlewareInterface));
    }

    protected function getPostSchedulingMiddleware(): MiddlewareRegistryInterface
    {
        return $this->orderMiddleware($this->middlewareRegistry->filter(static fn (object $middleware): bool => $middleware instanceof PostSchedulingMiddlewareInterface));
    }

    protected function getPreExecutionMiddleware(): MiddlewareRegistryInterface
    {
        return $this->orderMiddleware($this->middlewareRegistry->filter(static fn (object $middleware): bool => $middleware instanceof PreExecutionMiddlewareInterface));
    }

    protected function getPostExecutionMiddleware(): MiddlewareRegistryInterface
    {
        return $this->orderMiddleware($this->middlewareRegistry->filter(static fn (object $middleware): bool => $middleware instanceof PostExecutionMiddlewareInterface));
    }

    protected function runMiddleware(MiddlewareRegistryInterface $middlewareList, Closure $func): void
    {
        $requiredMiddlewareList = $middlewareList->filter(static fn (object $middleware): bool => $middleware instanceof RequiredMiddlewareInterface);

        try {
            $middlewareList->walk(function (object $middleware) use ($func): void {
                $func($middleware);

                $this->executedMiddleware->attach($middleware);
            });
        } catch (Throwable $throwable) {
            foreach ($requiredMiddlewareList as $singleRequiredMiddlewareList) {
                if ($this->executedMiddleware->contains($singleRequiredMiddlewareList)) {
                    continue;
                }

                $func($singleRequiredMiddlewareList);
            }

            throw $throwable;
        }
    }

    private function orderMiddleware(MiddlewareRegistryInterface $registry): MiddlewareRegistryInterface
    {
        $orderedMiddleware = $registry->filter(static fn (object $middleware): bool => $middleware instanceof OrderedMiddlewareInterface);

        if (0 === $orderedMiddleware->count()) {
            return $registry;
        }

        $orderedMiddleware->uasort(static fn (OrderedMiddlewareInterface $middleware, OrderedMiddlewareInterface $nextMiddleware): int => $middleware->getPriority() <=> $nextMiddleware->getPriority());

        return new MiddlewareRegistry(array_values(array_replace($orderedMiddleware->toArray(), $registry->toArray())));
    }
}
