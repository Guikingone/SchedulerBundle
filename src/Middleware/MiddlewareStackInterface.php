<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface MiddlewareStackInterface
{
    /**
     * Return the middleware used by a specific middleware stack.
     *
     * @return array<int, mixed>
     */
    public function getMiddlewareList(): array;
}
