<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskUnscheduledEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUnscheduledEventTest extends TestCase
{
    public function testEventCanReturnTaskName(): void
    {
        $taskUnscheduledEvent = new TaskUnscheduledEvent('foo');

        self::assertSame('foo', $taskUnscheduledEvent->getTask());
    }
}
