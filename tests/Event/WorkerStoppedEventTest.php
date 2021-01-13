<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Tests\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerStoppedEventTest extends TestCase
{
    public function testWorkerIsAccessible(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $event = new WorkerStoppedEvent($worker);
        static::assertSame($worker, $event->getWorker());
    }
}
