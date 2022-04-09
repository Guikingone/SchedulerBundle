<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\FiberScheduler;
use SchedulerBundle\Messenger\TaskToPauseMessage;
use SchedulerBundle\Messenger\TaskToUpdateMessage;
use SchedulerBundle\Messenger\TaskToYieldMessage;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\Middleware\TaskLockBagMiddleware;
use SchedulerBundle\Middleware\TaskUpdateMiddleware;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Runner\RunnerRegistry;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Scheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Transport\TransportInterface;
use SchedulerBundle\Worker\Worker;
use SchedulerBundle\Worker\WorkerConfiguration;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Throwable;
use Generator;

/**
 * @requires PHP 8.1
 */
final class FiberSchedulerTest extends TestCase
{
    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanSchedule(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));
        $fiberScheduler->schedule(new NullTask('foo'));

        $task = $fiberScheduler->getTasks()->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanUnscheduleWhenNotInitialized(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));
        $fiberScheduler->unschedule('foo');

        self::assertCount(0, $fiberScheduler->getTasks());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanUnschedule(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        $fiberScheduler->schedule(new NullTask('foo'));
        self::assertCount(1, $fiberScheduler->getTasks());

        $fiberScheduler->unschedule('foo');
        self::assertCount(0, $fiberScheduler->getTasks());
    }

    /**
     * @throws Exception {@see SchedulerInterface::__construct()}
     * @throws Throwable {@see SchedulerInterface::yieldTask()}
     */
    public function testSchedulerCannotYieldUndefinedTask(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $fiberScheduler->yieldTask('foo');
    }

    /**
     * @throws Exception {@see SchedulerInterface::__construct()}
     * @throws Throwable {@see SchedulerInterface::yieldTask()}
     */
    public function testSchedulerCanYield(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));
        $fiberScheduler->schedule(new NullTask('foo'));
        $fiberScheduler->yieldTask('foo');

        self::assertCount(1, $fiberScheduler->getTasks());
    }

    /**
     * @throws Exception {@see SchedulerInterface::__construct()}
     * @throws Throwable {@see SchedulerInterface::yieldTask()}
     */
    public function testSchedulerCanYieldAsynchronously(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TaskToYieldMessage('foo'))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));
        $fiberScheduler->schedule(new NullTask('foo'));

        $fiberScheduler->yieldTask('foo', true);
        self::assertCount(0, $fiberScheduler->getTasks());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testSchedulerCannotPauseUndefinedTask(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $fiberScheduler->pause('foo');
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanPause(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));
        $fiberScheduler->schedule(new NullTask('foo'));
        $fiberScheduler->pause('foo');

        $pausedTask = $fiberScheduler->getTasks()->get('foo');
        self::assertInstanceOf(NullTask::class, $pausedTask);
        self::assertSame(TaskInterface::PAUSED, $pausedTask->getState());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanPauseAsynchronously(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TaskToPauseMessage('foo'))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));
        $fiberScheduler->schedule(new NullTask('foo'));
        $fiberScheduler->pause('foo', true);

        $pausedTask = $fiberScheduler->getTasks()->get('foo');
        self::assertInstanceOf(NullTask::class, $pausedTask);
        self::assertSame(TaskInterface::ENABLED, $pausedTask->getState());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCannotResumeUndefinedTask(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $fiberScheduler->resume('foo');
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanResume(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));
        $fiberScheduler->schedule(new NullTask('foo'));
        $fiberScheduler->pause('foo');

        $task = $fiberScheduler->getTasks()->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        $fiberScheduler->resume('foo');

        $task = $fiberScheduler->getTasks()->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanGetTasksWhenEmpty(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        $tasks = $fiberScheduler->getTasks();
        self::assertInstanceOf(TaskList::class, $tasks);
        self::assertCount(0, $tasks);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanGetTasks(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);

        $fiberScheduler->schedule(new NullTask('foo'));
        $fiberScheduler->schedule(new NullTask('bar'));

        $tasks = $fiberScheduler->getTasks();
        self::assertInstanceOf(TaskList::class, $tasks);
        self::assertCount(2, $tasks);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanGetTasksLazilyWhenEmpty(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);

        $tasks = $fiberScheduler->getTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $tasks);
        self::assertCount(0, $tasks);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanGetTasksLazily(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);

        $fiberScheduler->schedule(new NullTask('foo'));
        $fiberScheduler->schedule(new NullTask('bar'));

        $tasks = $fiberScheduler->getTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $tasks);
        self::assertCount(2, $tasks);

        $fiberScheduler->unschedule('foo');
        $fiberScheduler->unschedule('bar');

        $tasks = $fiberScheduler->getTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $tasks);
        self::assertCount(0, $tasks);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanGetDueTasksWhenEmpty(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);

        $tasks = $fiberScheduler->getDueTasks();
        self::assertInstanceOf(TaskList::class, $tasks);
        self::assertCount(0, $tasks);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanGetDueTasks(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);

        $fiberScheduler->schedule(new NullTask('foo'));
        $fiberScheduler->schedule(new NullTask('bar'));

        $tasks = $fiberScheduler->getDueTasks();
        self::assertInstanceOf(TaskList::class, $tasks);
        self::assertCount(2, $tasks);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanGetDueTasksLazilyWhenEmpty(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);

        $tasks = $fiberScheduler->getDueTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $tasks);
        self::assertCount(0, $tasks);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanGetDueTasksLazily(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);

        $fiberScheduler->schedule(new NullTask('foo'));
        $fiberScheduler->schedule(new NullTask('bar'));

        $tasks = $fiberScheduler->getDueTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $tasks);
        self::assertCount(2, $tasks);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotGetNextDueTasksLazilyWhenEmpty(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The current due tasks is empty');
        self::expectExceptionCode(0);
        $fiberScheduler->next();
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotGetNextDueTasksLazilyWhenASingleTaskIsFound(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);
        $fiberScheduler->schedule(new NullTask('foo'));

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The next due task cannot be found');
        self::expectExceptionCode(0);
        $fiberScheduler->next();
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanGetNextDueTasksWhenSingleTaskFound(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);
        $fiberScheduler->schedule(new NullTask('foo'));

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The next due task cannot be found');
        self::expectExceptionCode(0);
        $fiberScheduler->next(true);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanGetNextDueTasks(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $fiberScheduler = new FiberScheduler($scheduler);

        $fiberScheduler->schedule(new NullTask('foo'));
        $fiberScheduler->schedule(new NullTask('bar'));

        $nextTask = $fiberScheduler->next();
        self::assertInstanceOf(NullTask::class, $nextTask);
        self::assertSame('bar', $nextTask->getName());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanGetNextDueTasksLazily(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        $fiberScheduler->schedule(new NullTask('foo'));
        $fiberScheduler->schedule(new NullTask('bar'));

        $nextTask = $fiberScheduler->next(true);
        self::assertInstanceOf(LazyTask::class, $nextTask);
        self::assertFalse($nextTask->isInitialized());
        self::assertSame('bar.lazy', $nextTask->getName());

        $task = $nextTask->getTask();
        self::assertTrue($nextTask->isInitialized());
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('bar', $task->getName());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::reboot()}
     */
    public function testSchedulerCanReboot(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));
        $fiberScheduler->schedule(new NullTask('foo'));

        $fiberScheduler->reboot();
        self::assertCount(1, $fiberScheduler->getTasks());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testTimezoneCanBeReturned(): void
    {
        $fiberScheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        self::assertSame('UTC', $fiberScheduler->getTimezone()->getName());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCannotPreemptEmptyDueTasks(): void
    {
        $task = new NullTask('foo');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        $scheduler->preempt('foo', static fn (TaskInterface $task): bool => $task->getName() === 'bar');
        self::assertNotSame(TaskInterface::READY_TO_EXECUTE, $task->getState());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotPreemptEmptyToPreemptTasks(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::never())->method('addListener');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher));

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->preempt('foo', static fn (TaskInterface $task): bool => $task->getName() === 'bar');
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanPreemptTasks(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = new EventDispatcher();

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher));

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->schedule(new NullTask('bar'));
        $scheduler->schedule(new NullTask('reboot'));
        $scheduler->preempt('foo', fn (TaskInterface $task): bool => $task->getName() === 'reboot');

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), new TaskExecutionTracker(new Stopwatch()), new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);

        $worker->execute(WorkerConfiguration::create());
        self::assertCount(0, $worker->getFailedTasks());

        $lastExecutedTask = $worker->getLastExecutedTask();
        self::assertInstanceOf(NullTask::class, $lastExecutedTask);
        self::assertSame('bar', $lastExecutedTask->getName());

        $preemptTask = $scheduler->getTasks()->get('reboot');
        self::assertInstanceOf(NullTask::class, $preemptTask);
        self::assertInstanceOf(DateTimeImmutable::class, $preemptTask->getLastExecution());
        self::assertInstanceOf(DateTimeImmutable::class, $preemptTask->getExecutionStartTime());
        self::assertInstanceOf(DateTimeImmutable::class, $preemptTask->getExecutionEndTime());

        $fooTask = $scheduler->getTasks()->get('foo');
        self::assertInstanceOf(NullTask::class, $fooTask);
        self::assertInstanceOf(DateTimeImmutable::class, $fooTask->getLastExecution());
        self::assertInstanceOf(DateTimeImmutable::class, $fooTask->getExecutionStartTime());
        self::assertInstanceOf(DateTimeImmutable::class, $fooTask->getExecutionEndTime());

        $barTask = $scheduler->getTasks()->get('bar');
        self::assertInstanceOf(NullTask::class, $barTask);
        self::assertInstanceOf(DateTimeImmutable::class, $barTask->getLastExecution());
        self::assertInstanceOf(DateTimeImmutable::class, $barTask->getExecutionStartTime());
        self::assertInstanceOf(DateTimeImmutable::class, $barTask->getExecutionEndTime());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdated(TaskInterface $task): void
    {
        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getTasks());

        $task->addTag('new_tag');
        $scheduler->update($task->getName(), $task);

        $updatedTask = $scheduler->getTasks()->filter(static fn (TaskInterface $task): bool => in_array('new_tag', $task->getTags(), true));
        self::assertCount(1, $updatedTask);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testTaskCanBeUpdatedAsynchronously(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('create')->with(self::equalTo($task));
        $transport->expects(self::never())->method('update')->with(self::equalTo('foo'), self::equalTo($task));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TaskToUpdateMessage('foo', $task))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $scheduler = new FiberScheduler(new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));

        $scheduler->schedule($task);
        $scheduler->update($task->getName(), $task, true);
    }

    /**
     * @return Generator<array<int, ShellTask>>
     */
    public function provideTasks(): Generator
    {
        yield 'Shell tasks' => [
            new ShellTask('Bar', ['echo', 'Symfony']),
            new ShellTask('Foo', ['echo', 'Symfony']),
        ];
    }
}