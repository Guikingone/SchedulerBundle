<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Test\Constraint\TaskScheduled;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskScheduledTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $taskEventList = new TaskEventList();

        $taskScheduled = new TaskScheduled(1);

        self::assertFalse($taskScheduled->evaluate($taskEventList, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskUnscheduledEvent('foo'));
        $taskEventList->addEvent(new TaskScheduledEvent($task));

        $taskScheduled = new TaskScheduled(1);

        self::assertTrue($taskScheduled->evaluate($taskEventList, '', true));
    }
}
