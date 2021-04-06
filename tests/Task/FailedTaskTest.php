<?php

declare(strict_types=1);

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
        $task->expects(self::once())->method('getName')->willReturn('bar');

        $failedTask = new FailedTask($task, 'foo');

        self::assertSame('bar.failed', $failedTask->getName());
        self::assertSame($task, $failedTask->getTask());
        self::assertSame('foo', $failedTask->getReason());
    }
}
