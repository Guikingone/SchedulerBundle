<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailedTaskTest extends TestCase
{
    public function testTaskReceiveValidName(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('bar');

        $failedTask = new FailedTask($task, 'foo');

        static::assertSame('bar.failed', $failedTask->getName());
        static::assertSame($task, $failedTask->getTask());
        static::assertSame('foo', $failedTask->getReason());
        static::assertInstanceOf(\DateTimeInterface::class, $failedTask->getFailedAt());
    }
}
