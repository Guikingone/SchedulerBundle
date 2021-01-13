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
use SchedulerBundle\Event\SchedulerRebootedEvent;
use SchedulerBundle\SchedulerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerRebootedEventTest extends TestCase
{
    public function testSchedulerCanBeRetrieved(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $event = new SchedulerRebootedEvent($scheduler);
        static::assertSame($scheduler, $event->getScheduler());
    }
}
