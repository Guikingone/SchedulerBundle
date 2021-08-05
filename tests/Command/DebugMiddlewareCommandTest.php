<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Command\DebugMiddlewareCommand;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DebugMiddlewareCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $command = new DebugMiddlewareCommand(new SchedulerMiddlewareStack([]), new WorkerMiddlewareStack([]));

        self::assertSame('scheduler:debug:middleware', $command->getName());
    }
}
