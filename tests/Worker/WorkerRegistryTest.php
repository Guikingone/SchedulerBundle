<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker;

use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\RunnerRegistry;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Worker\ExecutionPolicy\ExecutionPolicyRegistry;
use SchedulerBundle\Worker\Worker;
use SchedulerBundle\Worker\WorkerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerRegistryTest extends TestCase
{
    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testRegistryCanCount(): void
    {
        $registry = new WorkerRegistry(workers: [
            new Worker(
                scheduler: new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
                    new FirstInFirstOutPolicy(),
                ])), middlewareStack: new SchedulerMiddlewareStack([]), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore())),
                runnerRegistry: new RunnerRegistry([]),
                executionPolicyRegistry: new ExecutionPolicyRegistry([]),
                taskExecutionTracker: new TaskExecutionTracker(new Stopwatch()),
                middlewareStack: new WorkerMiddlewareStack(),
                eventDispatcher: new EventDispatcher(),
                lockFactory: new LockFactory(new InMemoryStore()),
                logger: new NullLogger()
            ),
        ]);

        self::assertCount(expectedCount: 1, haystack: $registry);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testRegistryCanReturnWorkers(): void
    {
        $registry = new WorkerRegistry(workers: [
            new Worker(
                scheduler: new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
                    new FirstInFirstOutPolicy(),
                ])), middlewareStack: new SchedulerMiddlewareStack([]), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore())),
                runnerRegistry: new RunnerRegistry([]),
                executionPolicyRegistry: new ExecutionPolicyRegistry([]),
                taskExecutionTracker: new TaskExecutionTracker(new Stopwatch()),
                middlewareStack: new WorkerMiddlewareStack(),
                eventDispatcher: new EventDispatcher(),
                lockFactory: new LockFactory(new InMemoryStore()),
                logger: new NullLogger()
            ),
        ]);

        self::assertCount(expectedCount: 1, haystack: $registry->getWorkers());
    }
}
