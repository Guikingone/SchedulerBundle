<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerStartedEventTest extends TestCase
{
    public function testEventReturnWorkerState(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $event = new WorkerStartedEvent($worker);

        static::assertSame($worker, $event->getWorker());
    }
}
