<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\MiddlewareRegistry;
use SchedulerBundle\Middleware\RequiredMiddlewareInterface;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MiddlewareRegistryTest extends TestCase
{
    public function testRegistryCanFilterMiddlewareList(): void
    {
        $registry = new MiddlewareRegistry([
            new TaskCallbackMiddleware(),
        ]);

        $filteredRegistry = $registry->filter(static fn (object $middleware): bool => $middleware instanceof RequiredMiddlewareInterface);

        self::assertSame(0, $filteredRegistry->count());
        self::assertCount(1, $registry->toArray());
    }
}
