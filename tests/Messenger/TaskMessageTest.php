<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Messenger;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Messenger\TaskMessage;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskMessageTest extends TestCase
{
    public function testTaskCanBeSet(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('app.messenger');

        $message = new TaskMessage($task);
        static::assertSame($task, $message->getTask());
        static::assertSame('app.messenger', $message->getTask()->getName());
    }

    public function testWorkerTimeoutCanBeSet(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('app.messenger');

        $message = new TaskMessage($task, 2);
        static::assertSame($task, $message->getTask());
        static::assertSame('app.messenger', $message->getTask()->getName());
        static::assertSame(2, $message->getWorkerTimeout());
    }
}
