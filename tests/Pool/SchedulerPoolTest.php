<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Pool;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\MiddlewareRegistry;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Pool\SchedulerPool;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPoolTest extends TestCase
{
    public function testPoolCanAddScheduler(): void
    {
        $pool = new SchedulerPool();

        self::assertCount(0, $pool);

        $pool->add('https://127.0.0.1:9090', new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(new MiddlewareRegistry([])), new EventDispatcher()));

        self::assertCount(1, $pool);
    }

    public function testPoolCanAddSchedulerAndReturnIt(): void
    {
        $pool = new SchedulerPool();

        self::assertCount(0, $pool);

        $pool->add('https://127.0.0.1:9090', new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(new MiddlewareRegistry([])), new EventDispatcher()));

        self::assertCount(1, $pool);

        $scheduler = $pool->get('https://127.0.0.1:9090');
        self::assertCount(0, $scheduler->getTasks());
    }
}
