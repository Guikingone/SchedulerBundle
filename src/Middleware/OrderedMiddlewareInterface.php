<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface OrderedMiddlewareInterface
{
    /**
     * Defines the priority of the middleware when filtering it in the corresponding stack.
     *
     * The closer the priority to 1, the earlier the middleware is called.
     */
    public function getPriority(): int;
}
