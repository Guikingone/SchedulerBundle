<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerSleepingEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\EventListener\StopWorkerOnSignalSubscriber;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension pcntl
 */
final class StopWorkerOnSignalSubscriberTest extends TestCase
{
    public function testEventsAreSubscribed(): void
    {
        self::assertCount(4, StopWorkerOnSignalSubscriber::getSubscribedEvents());

        self::assertArrayHasKey(TaskExecutingEvent::class, StopWorkerOnSignalSubscriber::getSubscribedEvents());
        self::assertIsArray(StopWorkerOnSignalSubscriber::getSubscribedEvents()[TaskExecutingEvent::class]);
        self::assertContains(100, StopWorkerOnSignalSubscriber::getSubscribedEvents()[TaskExecutingEvent::class]);
        self::assertContains('onTaskExecuting', StopWorkerOnSignalSubscriber::getSubscribedEvents()[TaskExecutingEvent::class]);

        self::assertArrayHasKey(WorkerStartedEvent::class, StopWorkerOnSignalSubscriber::getSubscribedEvents());
        self::assertIsArray(StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);
        self::assertContains(100, StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);
        self::assertContains('onWorkerStarted', StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);

        self::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnSignalSubscriber::getSubscribedEvents());
        self::assertIsArray(StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerRunningEvent::class]);
        self::assertContains(100, StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerRunningEvent::class]);
        self::assertContains('onWorkerRunning', StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerRunningEvent::class]);

        self::assertArrayHasKey(WorkerSleepingEvent::class, StopWorkerOnSignalSubscriber::getSubscribedEvents());
        self::assertIsArray(StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerSleepingEvent::class]);
        self::assertContains(100, StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerSleepingEvent::class]);
        self::assertContains('onWorkerSleeping', StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerSleepingEvent::class]);
    }
}
