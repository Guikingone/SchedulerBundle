<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\EventListener\StopWorkerOnNextTaskSubscriber;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnNextTaskSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        self::assertArrayHasKey(WorkerStartedEvent::class, StopWorkerOnNextTaskSubscriber::getSubscribedEvents());
        self::assertSame('onWorkerStarted' , StopWorkerOnNextTaskSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);
        self::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnNextTaskSubscriber::getSubscribedEvents());
        self::assertSame('onWorkerRunning' , StopWorkerOnNextTaskSubscriber::getSubscribedEvents()[WorkerRunningEvent::class]);
    }

    public function testSubscriberCanStopWorker(): void
    {
    }
}
