<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Test\Constraint\TaskExecuted;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutedTest extends TestCase
{
    public function testConstraintCannotMatchWithoutEvent(): void
    {
        $taskEventList = new TaskEventList();

        $taskExecuted = new TaskExecuted(1);

        self::assertFalse($taskExecuted->evaluate($taskEventList, '', true));
    }

    public function testConstraintCannotMatchWithoutValidEvent(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getExecutionState');

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskScheduledEvent($task));

        $taskExecuted = new TaskExecuted(1);

        self::assertFalse($taskExecuted->evaluate($taskEventList, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionState')->willReturn(TaskInterface::SUCCEED);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskScheduledEvent($task));
        $taskEventList->addEvent(new TaskExecutedEvent($task, new Output($task)));

        $taskExecuted = new TaskExecuted(1);

        self::assertTrue($taskExecuted->evaluate($taskEventList, '', true));
    }
}
