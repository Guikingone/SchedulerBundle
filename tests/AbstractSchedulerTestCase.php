<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\FiberScheduler;
use SchedulerBundle\LazyScheduler;
use SchedulerBundle\Middleware\MiddlewareRegistry;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Scheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractSchedulerTestCase extends TestCase
{
    /**
     * @throws Exception {@see DateTimeImmutable::__construct()}
     */
    abstract protected function getScheduler(): SchedulerInterface|FiberScheduler|LazyScheduler;

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCanScheduleTasks(): void
    {
        $scheduler = $this->getScheduler();

        $scheduler->schedule(task: new NullTask(name: 'foo'));

        self::assertCount(expectedCount: 1, haystack: $scheduler->getTasks());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCanScheduleTasksWithCustomTimezone(): void
    {
        $scheduler = $this->getScheduler();

        $scheduler->schedule(task: new NullTask(name: 'foo', options: [
            'timezone' => new DateTimeZone(timezone: 'Europe/Paris'),
        ]));

        self::assertCount(expectedCount: 1, haystack: $scheduler->getTasks());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see FiberScheduler::getTimezone()}
     */
    public function testSchedulerCanReturnTheTimezone(): void
    {
        $scheduler = $this->getScheduler();

        $timezone = $scheduler->getTimezone();
        self::assertSame(expected: 'UTC', actual: $timezone->getName());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanReturnNextDueTask(): void
    {
        $scheduler = $this->getScheduler();

        $scheduler->schedule(task: new NullTask(name: 'foo'));
        $scheduler->schedule(task: new NullTask(name: 'bar'));

        self::assertCount(expectedCount: 2, haystack: $scheduler->getDueTasks());

        $nextDueTask = $scheduler->next();
        self::assertInstanceOf(expected: NullTask::class, actual: $nextDueTask);
        self::assertSame(expected: 'bar', actual: $nextDueTask->getName());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanReturnNextDueTaskAsynchronously(): void
    {
        $scheduler = $this->getScheduler();

        $scheduler->schedule(task: new NullTask(name: 'foo'));
        $scheduler->schedule(task: new NullTask(name: 'bar'));

        $nextDueTask = $scheduler->next(lazy: true);
        self::assertInstanceOf(expected: LazyTask::class, actual: $nextDueTask);
        self::assertFalse(condition: $nextDueTask->isInitialized());
        self::assertSame(expected: 'bar.lazy', actual: $nextDueTask->getName());

        $task = $nextDueTask->getTask();
        self::assertTrue(condition: $nextDueTask->isInitialized());
        self::assertInstanceOf(expected: NullTask::class, actual: $task);
        self::assertSame(expected: 'bar', actual: $task->getName());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testSchedulerCanRebootWithEmptyTasks(): void
    {
        $scheduler = $this->getScheduler();

        $scheduler->schedule(task: new NullTask(name: 'bar'));
        self::assertCount(expectedCount: 1, haystack: $scheduler->getTasks());

        $scheduler->reboot();
        self::assertCount(expectedCount: 0, haystack: $scheduler->getTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testSchedulerCanReboot(): void
    {
        $scheduler = $this->getScheduler();
        $scheduler->schedule(task: new NullTask(name: 'foo', options: [
            'expression' => '@reboot',
        ]));

        $scheduler->schedule(task: new NullTask(name: 'bar'));
        self::assertCount(expectedCount: 2, haystack: $scheduler->getTasks());

        $scheduler->reboot();
        self::assertCount(expectedCount: 1, haystack: $scheduler->getTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCannotPreemptEmptyDueTasks(): void
    {
        $task = new NullTask(name: 'foo');

        $scheduler = $this->getScheduler();

        $scheduler->preempt(taskToPreempt: 'foo', filter: fn (TaskInterface $task): bool => $task->getName() === 'bar');
        self::assertNotSame(expected: TaskInterface::READY_TO_EXECUTE, actual: $task->getState());
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getPoolConfiguration()}
     */
    public function testSchedulerPoolConfigurationIsAvailable(): void
    {
        $scheduler = $this->getScheduler();

        $poolConfiguration = $scheduler->getPoolConfiguration();
        self::assertSame(expected: 'UTC', actual: $poolConfiguration->getTimezone()->getName());
        self::assertArrayNotHasKey(key: 'foo', array: $poolConfiguration->getDueTasks());
        self::assertCount(expectedCount: 0, haystack: $poolConfiguration->getDueTasks());
    }
}
