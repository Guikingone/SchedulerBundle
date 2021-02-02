<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Test\Constraint\TaskQueued;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskQueuedTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $list = new TaskEventList();

        $constraint = new TaskQueued(1);

        self::assertFalse($constraint->evaluate($list, '', true));
    }

    public function testConstraintCannotMatchWithoutQueuedTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $list = new TaskEventList();
        $list->addEvent(new TaskExecutedEvent($task));

        $constraint = new TaskQueued(1);

        self::assertFalse($constraint->evaluate($list, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('isQueued')->willReturn(true);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::never())->method('isQueued')->willReturn(false);

        $list = new TaskEventList();
        $list->addEvent(new TaskExecutedEvent($task));
        $list->addEvent(new TaskExecutedEvent($secondTask));
        $list->addEvent(new TaskScheduledEvent($task));

        $constraint = new TaskQueued(1);

        self::assertTrue($constraint->evaluate($list, '', true));
    }
}
