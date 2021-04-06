<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskFailedEventTest extends TestCase
{
    public function testTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $failedTask = new FailedTask($task, 'error');

        $taskFailedEvent = new TaskFailedEvent($failedTask);

        self::assertSame($failedTask, $taskFailedEvent->getTask());
        self::assertSame($task, $taskFailedEvent->getTask()->getTask());
    }
}
