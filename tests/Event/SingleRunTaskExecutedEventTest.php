<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\SingleRunTaskExecutedEvent;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SingleRunTaskExecutedEventTest extends TestCase
{
    public function testEventReturnTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = new SingleRunTaskExecutedEvent($task);
        self::assertSame($task, $event->getTask());
    }
}
