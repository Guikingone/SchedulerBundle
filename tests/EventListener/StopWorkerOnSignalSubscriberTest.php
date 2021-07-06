<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
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
        self::assertArrayHasKey(WorkerStartedEvent::class, StopWorkerOnSignalSubscriber::getSubscribedEvents());
        self::assertContains(100, StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);
        self::assertContains('onWorkerStarted', StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);
        self::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnSignalSubscriber::getSubscribedEvents());
        self::assertContains(100, StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerRunningEvent::class]);
        self::assertContains('onWorkerRunning', StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerRunningEvent::class]);
        self::assertArrayHasKey(WorkerSleepingEvent::class, StopWorkerOnSignalSubscriber::getSubscribedEvents());
        self::assertContains(100, StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerSleepingEvent::class]);
        self::assertContains('onWorkerSleeping', StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerSleepingEvent::class]);
    }
}
