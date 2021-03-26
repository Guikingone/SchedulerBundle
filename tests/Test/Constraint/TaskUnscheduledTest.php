<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Test\Constraint\TaskUnscheduled;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUnscheduledTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $taskEventList = new TaskEventList();

        $taskUnscheduled = new TaskUnscheduled(1);

        self::assertFalse($taskUnscheduled->evaluate($taskEventList, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskScheduledEvent($task));
        $taskEventList->addEvent(new TaskUnscheduledEvent('foo'));

        $taskUnscheduled = new TaskUnscheduled(1);

        self::assertTrue($taskUnscheduled->evaluate($taskEventList, '', true));
    }
}
