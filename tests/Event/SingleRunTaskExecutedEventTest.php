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
use SchedulerBundle\Event\SingleRunTaskExecutedEvent;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SingleRunTaskExecutedEventTest extends TestCase
{
    public function testEventReturnTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = new SingleRunTaskExecutedEvent($task);
        static::assertSame($task, $event->getTask());
    }
}
