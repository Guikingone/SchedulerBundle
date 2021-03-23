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
        $list = new TaskEventList();

        $constraint = new TaskFailed(1);

        self::assertFalse($constraint->evaluate($list, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $list = new TaskEventList();
        $list->addEvent(new TaskUnscheduledEvent('foo'));
        $list->addEvent(new TaskFailedEvent(new FailedTask($task, 'error')));

        $constraint = new TaskFailed(1);

        self::assertTrue($constraint->evaluate($list, '', true));
    }
}
