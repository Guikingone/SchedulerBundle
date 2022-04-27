<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\TaskUpdateMiddleware;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUpdateMiddlewareTest extends TestCase
{
    public function testMiddlewareIsConfigured(): void
    {
        $middleware = new TaskUpdateMiddleware(new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));

        self::assertSame(10, $middleware->getPriority());
    }

    /**
     * @throws Throwable {@see PostExecutionMiddlewareInterface::postExecute()}
     */
    public function testMiddlewareCanUpdate(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $task = new NullTask('foo');

        $transport = new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create($task);

        $taskUpdateMiddleware = new TaskUpdateMiddleware($transport);
        $taskUpdateMiddleware->postExecute($task, $worker);

        self::assertSame('foo', $task->getName());
    }
}
