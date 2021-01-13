<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Test\Constraint\TaskScheduled;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskScheduledTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $list = new TaskEventList();

        $constraint = new TaskScheduled(1);

        self::assertFalse($constraint->evaluate($list, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $list = new TaskEventList();
        $list->addEvent(new TaskScheduledEvent($task));

        $constraint = new TaskScheduled(1);

        self::assertTrue($constraint->evaluate($list, '', true));
    }
}
