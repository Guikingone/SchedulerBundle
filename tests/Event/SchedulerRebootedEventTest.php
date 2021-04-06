<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\SchedulerRebootedEvent;
use SchedulerBundle\SchedulerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerRebootedEventTest extends TestCase
{
    public function testSchedulerCanBeRetrieved(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $schedulerRebootedEvent = new SchedulerRebootedEvent($scheduler);
        self::assertSame($scheduler, $schedulerRebootedEvent->getScheduler());
    }
}
