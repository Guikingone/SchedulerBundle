<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Test\Constraint;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Test\Constraint\TaskUnscheduled;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUnscheduledTest extends TestCase
{
    public function testConstraintCannotMatch(): void
    {
        $list = new TaskEventList();

        $constraint = new TaskUnscheduled(1);

        self::assertFalse($constraint->evaluate($list, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $list = new TaskEventList();
        $list->addEvent(new TaskUnscheduledEvent('foo'));

        $constraint = new TaskUnscheduled(1);

        self::assertTrue($constraint->evaluate($list, '', true));
    }
}
