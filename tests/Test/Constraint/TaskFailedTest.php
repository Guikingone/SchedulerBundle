<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Test\Constraint\TaskFailed;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskFailedTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $taskEventList = new TaskEventList();

        $taskFailed = new TaskFailed(1);

        self::assertFalse($taskFailed->evaluate($taskEventList, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskEventList = new TaskEventList();
        $taskEventList->addEvent(new TaskUnscheduledEvent('foo'));
        $taskEventList->addEvent(new TaskFailedEvent(new FailedTask($task, 'error')));

        $taskFailed = new TaskFailed(1);

        self::assertTrue($taskFailed->evaluate($taskEventList, '', true));
    }
}
