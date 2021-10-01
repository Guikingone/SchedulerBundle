<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use DateTimeImmutable;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\LazyScheduler;
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
use function in_array;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazySchedulerTest extends TestCase
{
    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanSchedule(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertTrue($lazyScheduler->isInitialized());

        $task = $lazyScheduler->getTasks()->get('foo');
        self::assertTrue($lazyScheduler->isInitialized());
        self::assertInstanceOf(NullTask::class, $task);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanUnscheduleWhenNotInitialized(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->unschedule('foo');
        self::assertTrue($lazyScheduler->isInitialized());
        self::assertCount(0, $lazyScheduler->getTasks());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanUnschedule(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertCount(1, $lazyScheduler->getTasks());

        $lazyScheduler->unschedule('foo');
        self::assertTrue($lazyScheduler->isInitialized());
        self::assertCount(0, $lazyScheduler->getTasks());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testSchedulerCannotYieldUndefinedTask(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $lazyScheduler->yieldTask('foo');
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testSchedulerCanYield(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus);

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertTrue($lazyScheduler->isInitialized());

        $lazyScheduler->yieldTask('foo');
        self::assertTrue($lazyScheduler->isInitialized());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testSchedulerCanYieldAsynchronously(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TaskToYieldMessage('foo'))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus);

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertTrue($lazyScheduler->isInitialized());

        $lazyScheduler->yieldTask('foo', true);
        self::assertTrue($lazyScheduler->isInitialized());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testSchedulerCannotPauseUndefinedTask(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $lazyScheduler->pause('foo');
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanPause(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus);

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertTrue($lazyScheduler->isInitialized());

        $lazyScheduler->pause('foo');
        self::assertTrue($lazyScheduler->isInitialized());

        $pausedTask = $lazyScheduler->getTasks()->get('foo');
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

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus);

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertTrue($lazyScheduler->isInitialized());

        $lazyScheduler->pause('foo', true);
        self::assertTrue($lazyScheduler->isInitialized());

        $pausedTask = $lazyScheduler->getTasks()->get('foo');
        self::assertInstanceOf(NullTask::class, $pausedTask);
        self::assertSame(TaskInterface::ENABLED, $pausedTask->getState());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCannotResumeUndefinedTask(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $lazyScheduler->resume('foo');
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanResume(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertTrue($lazyScheduler->isInitialized());

        $lazyScheduler->pause('foo');
        self::assertTrue($lazyScheduler->isInitialized());

        $task = $lazyScheduler->getTasks()->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        $lazyScheduler->resume('foo');
        self::assertTrue($lazyScheduler->isInitialized());

        $task = $lazyScheduler->getTasks()->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanGetTasksWhenEmpty(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $tasks = $lazyScheduler->getTasks();
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        $lazyScheduler->schedule(new NullTask('bar'));
        self::assertTrue($lazyScheduler->isInitialized());

        $tasks = $lazyScheduler->getTasks();
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $tasks = $lazyScheduler->getTasks(true);
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        $lazyScheduler->schedule(new NullTask('bar'));
        self::assertTrue($lazyScheduler->isInitialized());

        $tasks = $lazyScheduler->getTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $tasks);
        self::assertCount(2, $tasks);

        $lazyScheduler->unschedule('foo');
        $lazyScheduler->unschedule('bar');

        $tasks = $lazyScheduler->getTasks(true);
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $tasks = $lazyScheduler->getDueTasks();
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        $lazyScheduler->schedule(new NullTask('bar'));
        self::assertTrue($lazyScheduler->isInitialized());

        $tasks = $lazyScheduler->getDueTasks();
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $tasks = $lazyScheduler->getDueTasks(true);
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        $lazyScheduler->schedule(new NullTask('bar'));
        self::assertTrue($lazyScheduler->isInitialized());

        $tasks = $lazyScheduler->getDueTasks(true);
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The current due tasks is empty');
        self::expectExceptionCode(0);
        $lazyScheduler->next();
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertTrue($lazyScheduler->isInitialized());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The next due task cannot be found');
        self::expectExceptionCode(0);
        $lazyScheduler->next();
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The next due task cannot be found');
        self::expectExceptionCode(0);
        $lazyScheduler->next(true);
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

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        $lazyScheduler->schedule(new NullTask('bar'));

        $nextTask = $lazyScheduler->next();
        self::assertTrue($lazyScheduler->isInitialized());
        self::assertInstanceOf(NullTask::class, $nextTask);
        self::assertSame('bar', $nextTask->getName());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanGetNextDueTasksLazily(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        $lazyScheduler->schedule(new NullTask('bar'));

        $nextTask = $lazyScheduler->next(true);
        self::assertInstanceOf(LazyTask::class, $nextTask);
        self::assertTrue($lazyScheduler->isInitialized());
        self::assertFalse($nextTask->isInitialized());
        self::assertSame('bar.lazy', $nextTask->getName());

        $task = $nextTask->getTask();
        self::assertTrue($lazyScheduler->isInitialized());
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
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('reboot');

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->reboot();
        self::assertTrue($lazyScheduler->isInitialized());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     */
    public function testTimezoneCanBeReturned(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $lazyScheduler = new LazyScheduler($scheduler);

        self::assertFalse($lazyScheduler->isInitialized());
        self::assertSame('UTC', $lazyScheduler->getTimezone()->getName());
        self::assertTrue($lazyScheduler->isInitialized());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCannotPreemptEmptyDueTasks(): void
    {
        $task = new NullTask('foo');

        $scheduler = new LazyScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));
        self::assertFalse($scheduler->isInitialized());

        $scheduler->preempt('foo', fn (TaskInterface $task): bool => $task->getName() === 'bar');
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

        $scheduler = new LazyScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher));
        self::assertFalse($scheduler->isInitialized());

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->preempt('foo', fn (TaskInterface $task): bool => $task->getName() === 'bar');
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanPreemptTasks(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = new EventDispatcher();

        $scheduler = new LazyScheduler(new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher));
        self::assertFalse($scheduler->isInitialized());

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
        $scheduler = new LazyScheduler(new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));
        self::assertFalse($scheduler->isInitialized());

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getTasks());
        self::assertTrue($scheduler->isInitialized());

        $task->addTag('new_tag');
        $scheduler->update($task->getName(), $task);
        self::assertTrue($scheduler->isInitialized());

        $updatedTask = $scheduler->getTasks()->filter(static fn (TaskInterface $task): bool => in_array('new_tag', $task->getTags(), true));
        self::assertCount(1, $updatedTask);
        self::assertTrue($scheduler->isInitialized());
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

        $scheduler = new LazyScheduler(new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));

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
