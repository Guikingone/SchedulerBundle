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
}
