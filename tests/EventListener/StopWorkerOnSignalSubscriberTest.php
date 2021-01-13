<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerRunningEvent;
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
        static::assertArrayHasKey(WorkerStartedEvent::class, StopWorkerOnSignalSubscriber::getSubscribedEvents());
        static::assertContains(100, StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);
        static::assertContains('onWorkerStarted', StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);
        static::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnSignalSubscriber::getSubscribedEvents());
        static::assertContains(100, StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerRunningEvent::class]);
        static::assertContains('onWorkerRunning', StopWorkerOnSignalSubscriber::getSubscribedEvents()[WorkerRunningEvent::class]);
    }
}
