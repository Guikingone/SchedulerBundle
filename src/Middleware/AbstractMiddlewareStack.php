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

    /**
     * @var SplObjectStorage<PostExecutionMiddlewareInterface|PreExecutionMiddlewareInterface|PreSchedulingMiddlewareInterface|PostSchedulingMiddlewareInterface|RequiredMiddlewareInterface|OrderedMiddlewareInterface, null>
     */
    private SplObjectStorage $executedMiddleware;

    /**
     * @param iterable|PreExecutionMiddlewareInterface[]|PostExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[] $stack
     */
    public function __construct(iterable $stack = [])
    {
        $this->middlewareRegistry = new MiddlewareRegistry(middlewareList: $stack);
        $this->executedMiddleware = new SplObjectStorage();
    }

    protected function getPreSchedulingMiddleware(): MiddlewareRegistryInterface
    {
        return $this->orderMiddleware(registry: $this->middlewareRegistry->filter(func: static fn (PostExecutionMiddlewareInterface|PreExecutionMiddlewareInterface|PreSchedulingMiddlewareInterface|PostSchedulingMiddlewareInterface|RequiredMiddlewareInterface|OrderedMiddlewareInterface $middleware): bool => $middleware instanceof PreSchedulingMiddlewareInterface));
    }

    protected function getPostSchedulingMiddleware(): MiddlewareRegistryInterface
    {
        return $this->orderMiddleware(registry: $this->middlewareRegistry->filter(func: static fn (PostExecutionMiddlewareInterface|PreExecutionMiddlewareInterface|PreSchedulingMiddlewareInterface|PostSchedulingMiddlewareInterface|RequiredMiddlewareInterface|OrderedMiddlewareInterface $middleware): bool => $middleware instanceof PostSchedulingMiddlewareInterface));
    }

    protected function getPreExecutionMiddleware(): MiddlewareRegistryInterface
    {
        return $this->orderMiddleware(registry: $this->middlewareRegistry->filter(func: static fn (PostExecutionMiddlewareInterface|PreExecutionMiddlewareInterface|PreSchedulingMiddlewareInterface|PostSchedulingMiddlewareInterface|RequiredMiddlewareInterface|OrderedMiddlewareInterface $middleware): bool => $middleware instanceof PreExecutionMiddlewareInterface));
    }

    protected function getPostExecutionMiddleware(): MiddlewareRegistryInterface
    {
        return $this->orderMiddleware(registry: $this->middlewareRegistry->filter(func: static fn (PostExecutionMiddlewareInterface|PreExecutionMiddlewareInterface|PreSchedulingMiddlewareInterface|PostSchedulingMiddlewareInterface|RequiredMiddlewareInterface|OrderedMiddlewareInterface $middleware): bool => $middleware instanceof PostExecutionMiddlewareInterface));
    }

    protected function runMiddleware(MiddlewareRegistryInterface $middlewareList, Closure $func): void
    {
        $requiredMiddlewareList = $middlewareList->filter(func: static fn (PostExecutionMiddlewareInterface|PreExecutionMiddlewareInterface|PreSchedulingMiddlewareInterface|PostSchedulingMiddlewareInterface|RequiredMiddlewareInterface|OrderedMiddlewareInterface $middleware): bool => $middleware instanceof RequiredMiddlewareInterface);

        try {
            $middlewareList->walk(func: function (PostExecutionMiddlewareInterface|PreExecutionMiddlewareInterface|PreSchedulingMiddlewareInterface|PostSchedulingMiddlewareInterface|RequiredMiddlewareInterface|OrderedMiddlewareInterface $middleware) use ($func): void {
                $func($middleware);

                $this->executedMiddleware->attach(object: $middleware);
            });
        } catch (Throwable $throwable) {
            foreach ($requiredMiddlewareList as $singleRequiredMiddlewareList) {
                if ($this->executedMiddleware->contains(object: $singleRequiredMiddlewareList)) {
                    continue;
                }

                $func($singleRequiredMiddlewareList);
            }

            throw $throwable;
        }
    }

    private function orderMiddleware(MiddlewareRegistryInterface $registry): MiddlewareRegistryInterface
    {
        $orderedMiddleware = $registry->filter(func: static fn (PostExecutionMiddlewareInterface|PreExecutionMiddlewareInterface|PreSchedulingMiddlewareInterface|PostSchedulingMiddlewareInterface|RequiredMiddlewareInterface|OrderedMiddlewareInterface $middleware): bool => $middleware instanceof OrderedMiddlewareInterface);

        if (0 === $orderedMiddleware->count()) {
            return $registry;
        }

        $orderedMiddleware->uasort(func: static fn (OrderedMiddlewareInterface $middleware, OrderedMiddlewareInterface $nextMiddleware): int => $middleware->getPriority() <=> $nextMiddleware->getPriority());

        return new MiddlewareRegistry(middlewareList: array_values(array: array_replace($orderedMiddleware->toArray(), $registry->toArray())));
    }
}
