<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Test\Constraint\TaskQueued;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskQueuedTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $taskEventList = new TaskEventList();

        $taskQueued = new TaskQueued(1);

        self::assertFalse($taskQueued->evaluate($taskEventList, '', true));
    }

    public function testConstraintCannotMatchWithoutQueuedTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskExecutedEvent($task, new Output($task)));

        $taskQueued = new TaskQueued(1);

        self::assertFalse($taskQueued->evaluate($taskEventList, '', true));

        self::expectException(ExpectationFailedException::class);
        self::expectExceptionMessage('contains 1 task that has been queued');
        self::expectExceptionCode(0);
        $taskQueued->evaluate($taskEventList);
    }

    public function testConstraintCanMatch(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('isQueued')->willReturn(true);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::never())->method('isQueued')->willReturn(false);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskExecutedEvent($task, new Output($task)));
        $taskEventList->addEvent(new TaskExecutedEvent($secondTask, new Output($task)));
        $taskEventList->addEvent(new TaskScheduledEvent($task));

        $taskQueued = new TaskQueued(1);

        self::assertTrue($taskQueued->evaluate($taskEventList, '', true));
    }
}
