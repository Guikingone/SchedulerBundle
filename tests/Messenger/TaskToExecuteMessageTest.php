<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Messenger\TaskToExecuteMessage;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToExecuteMessageTest extends TestCase
{
    public function testTaskCanBeSet(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('app.messenger');

        $taskMessage = new TaskToExecuteMessage($task);
        self::assertSame(1, $taskMessage->getWorkerTimeout());
        self::assertSame($task, $taskMessage->getTask());
        self::assertSame('app.messenger', $taskMessage->getTask()->getName());
    }

    public function testWorkerTimeoutCanBeSet(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('app.messenger');

        $taskMessage = new TaskToExecuteMessage($task, 2);
        self::assertSame($task, $taskMessage->getTask());
        self::assertSame('app.messenger', $taskMessage->getTask()->getName());
        self::assertSame(2, $taskMessage->getWorkerTimeout());
    }
}
