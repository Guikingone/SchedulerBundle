<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Messenger\TaskToExecuteMessage;
use SchedulerBundle\Messenger\TaskToExecuteMessageHandler;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Runner\RunnerRegistry;
use SchedulerBundle\Runner\ShellTaskRunner;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Worker\ExecutionPolicy\DefaultPolicy;
use SchedulerBundle\Worker\ExecutionPolicy\ExecutionPolicyRegistry;
use SchedulerBundle\Worker\Worker;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @group time-sensitive
 */
final class TaskToExecuteMessageHandlerTest extends TestCase
{
    public function testHandlerCanRunDueTaskWithoutASpecificTimezone(): void
    {
        $task = new NullTask(name: 'foo', options: [
            'timezone' => null,
        ]);

        $worker = new Worker(
            scheduler: new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
                new FirstInFirstOutPolicy(),
            ])), middlewareStack: new SchedulerMiddlewareStack([]), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore())),
            runnerRegistry: new RunnerRegistry(runners: [
                new NullTaskRunner(),
            ]),
            executionPolicyRegistry: new ExecutionPolicyRegistry(policies: [
                new DefaultPolicy(),
            ]),
            taskExecutionTracker: new TaskExecutionTracker(watch: new Stopwatch()),
            middlewareStack: new WorkerMiddlewareStack(),
            eventDispatcher: new EventDispatcher(),
            lockFactory: new LockFactory(store: new InMemoryStore()),
            logger: new NullLogger()
        );

        $taskMessageHandler = new TaskToExecuteMessageHandler(worker: $worker);

        ($taskMessageHandler)(taskMessage: new TaskToExecuteMessage(task: $task));

        self::assertInstanceOf(expected: DateTimeImmutable::class, actual: $task->getLastExecution());
    }

    public function testHandlerCanRunDueTask(): void
    {
        $shellTask = new ShellTask(name: 'foo', command: ['echo', 'Symfony']);
        $shellTask->setScheduledAt(scheduledAt: new DateTimeImmutable());
        $shellTask->setExpression(expression: '* * * * *');
        $shellTask->setTimezone(dateTimeZone: new DateTimeZone(timezone: 'UTC'));

        $worker = new Worker(
            scheduler: new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
                new FirstInFirstOutPolicy(),
            ])), middlewareStack: new SchedulerMiddlewareStack([]), eventDispatcher: new EventDispatcher(), lockFactory: new LockFactory(store: new InMemoryStore())),
            runnerRegistry: new RunnerRegistry(runners: [
                new ShellTaskRunner(),
            ]),
            executionPolicyRegistry: new ExecutionPolicyRegistry(policies: [
                new DefaultPolicy(),
            ]),
            taskExecutionTracker: new TaskExecutionTracker(watch: new Stopwatch()),
            middlewareStack: new WorkerMiddlewareStack(),
            eventDispatcher: new EventDispatcher(),
            lockFactory: new LockFactory(store: new InMemoryStore()),
            logger: new NullLogger()
        );

        $taskMessageHandler = new TaskToExecuteMessageHandler(worker: $worker);

        ($taskMessageHandler)(taskMessage: new TaskToExecuteMessage(task: $shellTask));

        self::assertInstanceOf(expected: DateTimeImmutable::class, actual: $shellTask->getLastExecution());
    }

    public function testHandlerCanWaitForAvailableWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')->with(self::equalTo('The task "foo" cannot be executed for now as the worker is currently running'));

        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);
        $shellTask->setScheduledAt(new DateTimeImmutable());
        $shellTask->setExpression('* * * * *');
        $shellTask->setTimezone(new DateTimeZone('UTC'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::exactly(3))->method('isRunning')->willReturnOnConsecutiveCalls(true, true, false);
        $worker->expects(self::once())->method('execute')->with(WorkerConfiguration::create(), $shellTask);

        $taskMessageHandler = new TaskToExecuteMessageHandler(worker: $worker, logger: $logger);

        ($taskMessageHandler)(taskMessage: new TaskToExecuteMessage($shellTask, 2));
    }
}
