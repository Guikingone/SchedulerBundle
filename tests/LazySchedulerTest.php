<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\LazyScheduler;
use SchedulerBundle\Messenger\TaskToPauseMessage;
use SchedulerBundle\Messenger\TaskToYieldMessage;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Scheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Transport\InMemoryTransport;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazySchedulerTest extends TestCase
{
    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanSchedule(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertTrue($lazyScheduler->isInitialized());

        $task = $lazyScheduler->getTasks()->get('foo');
        self::assertTrue($lazyScheduler->isInitialized());
        self::assertInstanceOf(NullTask::class, $task);
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanUnscheduleWhenNotInitialized(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->unschedule('foo');
        self::assertTrue($lazyScheduler->isInitialized());
        self::assertCount(0, $lazyScheduler->getTasks());
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanUnschedule(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertCount(1, $lazyScheduler->getTasks());

        $lazyScheduler->unschedule('foo');
        self::assertTrue($lazyScheduler->isInitialized());
        self::assertCount(0, $lazyScheduler->getTasks());
    }

    public function testSchedulerCannotYieldUndefinedTask(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $lazyScheduler->yieldTask('foo');
    }

    public function testSchedulerCanYield(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), null, $bus);

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertTrue($lazyScheduler->isInitialized());

        $lazyScheduler->yieldTask('foo');
        self::assertTrue($lazyScheduler->isInitialized());
    }

    public function testSchedulerCanYieldAsynchronously(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TaskToYieldMessage('foo'))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), null, $bus);

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        self::assertTrue($lazyScheduler->isInitialized());

        $lazyScheduler->yieldTask('foo', true);
        self::assertTrue($lazyScheduler->isInitialized());
    }

    public function testSchedulerCannotPauseUndefinedTask(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $lazyScheduler->pause('foo');
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanPause(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), null, $bus);

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
        ])), new SchedulerMiddlewareStack(), null, $bus);

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
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCannotResumeUndefinedTask(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $lazyScheduler->resume('foo');
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanResume(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

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
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanGetTasks(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

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
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanGetTasksLazily(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

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
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSchedulerCanGetDueTasks(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

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
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanGetDueTasksLazily(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        $lazyScheduler->schedule(new NullTask('bar'));
        self::assertTrue($lazyScheduler->isInitialized());

        $tasks = $lazyScheduler->getDueTasks(true);
        self::assertInstanceOf(TaskList::class, $tasks);
        self::assertCount(2, $tasks);
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotGetNextDueTasksLazilyWhenEmpty(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The current due tasks is empty');
        self::expectExceptionCode(0);
        $lazyScheduler->next();
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotGetNextDueTasksLazilyWhenASingleTaskIsFound(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

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
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanGetNextDueTasksWhenSingleTaskFound(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The next due task cannot be found');
        self::expectExceptionCode(0);
        $lazyScheduler->next(true);
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanGetNextDueTasks(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

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
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanGetNextDueTasksLazily(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);
        self::assertFalse($lazyScheduler->isInitialized());

        $lazyScheduler->schedule(new NullTask('foo'));
        $lazyScheduler->schedule(new NullTask('bar'));

        $nextTask = $lazyScheduler->next(true);
        self::assertTrue($lazyScheduler->isInitialized());
        self::assertInstanceOf(NullTask::class, $nextTask);
        self::assertSame('bar', $nextTask->getName());
    }

    /**
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

    public function testTimezoneCanBeReturned(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $lazyScheduler = new LazyScheduler($scheduler);

        self::assertFalse($lazyScheduler->isInitialized());
        self::assertSame('UTC', $lazyScheduler->getTimezone()->getName());
        self::assertTrue($lazyScheduler->isInitialized());
    }
}
