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
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPoolTest extends TestCase
{
    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testPoolCanAddScheduler(): void
    {
        $pool = new SchedulerPool();

        self::assertCount(0, $pool);

        $pool->add(endpoint: 'https://127.0.0.1:9090', scheduler: new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(options: [
            'execution_mode' => 'first_in_first_out',
        ]), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(new MiddlewareRegistry([])), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore())));

        self::assertCount(1, $pool);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testPoolCanAddSchedulerAndReturnIt(): void
    {
        $pool = new SchedulerPool();

        self::assertCount(0, $pool);

        $pool->add(endpoint: 'https://127.0.0.1:9090', scheduler: new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(options: [
            'execution_mode' => 'first_in_first_out',
        ]), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(new MiddlewareRegistry([])), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore())));

        self::assertCount(1, $pool);

        $scheduler = $pool->get(endpoint: 'https://127.0.0.1:9090');
        self::assertCount(0, $scheduler->getTasks());
    }
}
