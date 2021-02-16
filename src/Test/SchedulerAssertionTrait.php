<?php

declare(strict_types=1);

namespace SchedulerBundle\Test;

use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\EventListener\TaskLoggerSubscriber;
use SchedulerBundle\Test\Constraint\TaskExecuted;
use SchedulerBundle\Test\Constraint\TaskFailed;
use SchedulerBundle\Test\Constraint\TaskQueued;
use SchedulerBundle\Test\Constraint\TaskScheduled;
use SchedulerBundle\Test\Constraint\TaskUnscheduled;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
trait SchedulerAssertionTrait
{
    public static function assertTaskScheduledCount(int $count, string $message = ''): void
    {
        self::assertThat(self::getSchedulerEventList(), new TaskScheduled($count), $message);
    }

    public static function assertTaskUnscheduledCount(int $count, string $message = ''): void
    {
        self::assertThat(self::getSchedulerEventList(), new TaskUnscheduled($count), $message);
    }

    public static function assertTaskExecutedCount(int $count, string $message = ''): void
    {
        self::assertThat(self::getSchedulerEventList(), new TaskExecuted($count), $message);
    }

    public static function assertTaskQueuedCount(int $count, string $message = ''): void
    {
        self::assertThat(self::getSchedulerEventList(), new TaskQueued($count), $message);
    }

    public static function assertTaskFailedCount(int $count, string $message = ''): void
    {
        self::assertThat(self::getSchedulerEventList(), new TaskFailed($count), $message);
    }

    private static function getSchedulerEventList(): TaskEventList
    {
        if (self::$container->has(TaskLoggerSubscriber::class)) {
            return self::$container->get(TaskLoggerSubscriber::class)->getEvents();
        }

        static::fail('A client must have Scheduler enabled to make task assertions. Did you forget to require guikingone/scheduler-bundle?');
    }
}
