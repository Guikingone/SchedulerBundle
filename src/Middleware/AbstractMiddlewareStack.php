<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Closure;
use SplObjectStorage;
use Throwable;
use function array_filter;
use function array_replace;
use function array_values;
use function is_array;
use function iterator_to_array;
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
    private SplObjectStorage $executedMiddleware;

    /**
     * @param iterable|PreExecutionMiddlewareInterface[]|PostExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[] $stack
     */
    public function __construct(iterable $stack = [])
    {
        $this->stack = is_array($stack) ? $stack : iterator_to_array($stack, true);
        $this->executedMiddleware = new SplObjectStorage();
    }

    /**
     * @return PreSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[]
     */
    protected function getPreSchedulingMiddleware(): array
    {
        return $this->orderMiddleware(array_filter($this->stack, static fn (object $middleware): bool => $middleware instanceof PreSchedulingMiddlewareInterface));
    }

    /**
     * @return PostSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[]
     */
    protected function getPostSchedulingMiddleware(): array
    {
        return $this->orderMiddleware(array_filter($this->stack, static fn (object $middleware): bool => $middleware instanceof PostSchedulingMiddlewareInterface));
    }

    /**
     * @return PreExecutionMiddlewareInterface[]|OrderedMiddlewareInterface[]
     */
    protected function getPreExecutionMiddleware(): array
    {
        return $this->orderMiddleware(array_filter($this->stack, static fn (object $middleware): bool => $middleware instanceof PreExecutionMiddlewareInterface));
    }

    /**
     * @return PostExecutionMiddlewareInterface[]|OrderedMiddlewareInterface[]
     */
    protected function getPostExecutionMiddleware(): array
    {
        return $this->orderMiddleware(array_filter($this->stack, static fn (object $middleware): bool => $middleware instanceof PostExecutionMiddlewareInterface));
    }

    /**
     * @param PostExecutionMiddlewareInterface[]|PostSchedulingMiddlewareInterface[]|PreExecutionMiddlewareInterface[]|PreSchedulingMiddlewareInterface[]|OrderedMiddlewareInterface[] $middlewareList
     */
    protected function runMiddleware(array $middlewareList, Closure $func): void
    {
        $requiredMiddlewareList = array_filter($middlewareList, static fn (object $middleware): bool => $middleware instanceof RequiredMiddlewareInterface);

        try {
            foreach ($middlewareList as $singleMiddlewareList) {
                $func($singleMiddlewareList);

                $this->executedMiddleware->attach($singleMiddlewareList);
            }
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

    private function orderMiddleware(array $middlewareList): array
    {
        $orderedMiddleware = array_filter($middlewareList, static fn (object $middleware): bool => $middleware instanceof OrderedMiddlewareInterface);

        if ([] === $orderedMiddleware) {
            return $middlewareList;
        }

        uasort($orderedMiddleware, static fn (OrderedMiddlewareInterface $middleware, OrderedMiddlewareInterface $nextMiddleware): int => $middleware->getPriority() <=> $nextMiddleware->getPriority());

        return array_values(array_replace($orderedMiddleware, $middlewareList));
    }
}
