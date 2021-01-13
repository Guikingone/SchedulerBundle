<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

        static::assertFalse($constraint->evaluate($list, '', true));
    }

    public function testConstraintCannotMatchWithoutQueuedTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $list = new TaskEventList();
        $list->addEvent(new TaskExecutedEvent($task));

        $constraint = new TaskQueued(1);

        static::assertFalse($constraint->evaluate($list, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('isQueued')->willReturn(true);

        $list = new TaskEventList();
        $list->addEvent(new TaskExecutedEvent($task));
        $list->addEvent(new TaskScheduledEvent($task));

        $constraint = new TaskQueued(1);

        static::assertTrue($constraint->evaluate($list, '', true));
    }
}
