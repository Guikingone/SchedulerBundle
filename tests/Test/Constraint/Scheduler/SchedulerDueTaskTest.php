<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint\Scheduler;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Test\Constraint\Scheduler\SchedulerDueTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerDueTaskTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList());

        $constraint = new SchedulerDueTask(1);

        self::assertFalse($constraint->evaluate($scheduler, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $constraint = new SchedulerDueTask(1);

        self::assertTrue($constraint->evaluate($scheduler, '', true));
    }
}
