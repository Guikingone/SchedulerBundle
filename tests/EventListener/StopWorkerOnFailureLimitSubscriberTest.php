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
use Psr\Log\LoggerInterface;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\EventListener\StopWorkerOnFailureLimitSubscriber;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnFailureLimitSubscriberTest extends TestCase
{
    public function testSubscriberListenValidEvents(): void
    {
        static::assertArrayHasKey(TaskFailedEvent::class, StopWorkerOnFailureLimitSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnFailureLimitSubscriber::getSubscribedEvents());
    }

    public function testSubscriberCanStopWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');

        $workerStartedEvent = new WorkerRunningEvent($worker, true);

        $subscriber = new StopWorkerOnFailureLimitSubscriber(0, $logger);
        $subscriber->onTaskFailedEvent();
        $subscriber->onWorkerStarted($workerStartedEvent);
    }
}
