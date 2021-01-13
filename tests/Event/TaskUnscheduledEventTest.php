<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Event;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskUnscheduledEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskUnscheduledEventTest extends TestCase
{
    public function testEventCanReturnTaskName(): void
    {
        $event = new TaskUnscheduledEvent('foo');

        static::assertSame('foo', $event->getTask());
    }
}
