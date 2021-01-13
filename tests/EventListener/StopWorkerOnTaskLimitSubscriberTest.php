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
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnTaskLimitSubscriberTest extends TestCase
{
    public function testEventIsListened(): void
    {
        static::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnTaskLimitSubscriber::getSubscribedEvents());
    }

    public function testWorkerCannotBeStopped(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::never())->method('stop');

        $event = new WorkerRunningEvent($worker, true);

        $subscriber = new StopWorkerOnTaskLimitSubscriber(10);
        $subscriber->onWorkerRunning($event);
    }

    public function testWorkerCanBeStoppedWithoutLogger(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');

        $event = new WorkerRunningEvent($worker, false);
        $subscriber = new StopWorkerOnTaskLimitSubscriber(10);

        for ($i = 0; $i < 10; ++$i) {
            $subscriber->onWorkerRunning($event);
        }
    }

    public function testWorkerCanBeStoppedWithLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('stop');

        $event = new WorkerRunningEvent($worker, false);
        $subscriber = new StopWorkerOnTaskLimitSubscriber(10, $logger);

        for ($i = 0; $i < 10; ++$i) {
            $subscriber->onWorkerRunning($event);
        }
    }
}
