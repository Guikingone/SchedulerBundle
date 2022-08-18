<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use DateTimeZone;
use Exception;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\FiberScheduler;
use SchedulerBundle\LazyScheduler;
use SchedulerBundle\Scheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
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
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testSchedulerCanRebootWithEmptyTasks(): void
    {
        $scheduler = $this->getScheduler();

        $scheduler->schedule(new NullTask('bar'));
        self::assertCount(1, $scheduler->getTasks());

        $scheduler->reboot();
        self::assertCount(0, $scheduler->getTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testSchedulerCanReboot(): void
    {
        $scheduler = $this->getScheduler();

        $scheduler->schedule(new NullTask('foo', [
            'expression' => '@reboot',
        ]));
        $scheduler->schedule(new NullTask('bar'));
        self::assertCount(2, $scheduler->getTasks());

        $scheduler->reboot();
        self::assertCount(1, $scheduler->getTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCannotPreemptEmptyDueTasks(): void
    {
        $task = new NullTask('foo');

        $scheduler = $this->getScheduler();

        $scheduler->preempt('foo', static fn (TaskInterface $task): bool => 'bar' === $task->getName());
        self::assertNotSame(TaskInterface::READY_TO_EXECUTE, $task->getState());
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
