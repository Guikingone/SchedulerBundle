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

        static::assertFalse($constraint->evaluate($list, '', true));
    }

    public function testConstraintCanMatch(): void
    {
        $list = new TaskEventList();
        $list->addEvent(new TaskUnscheduledEvent('foo'));

        $constraint = new TaskUnscheduled(1);

        static::assertTrue($constraint->evaluate($list, '', true));
    }
}
