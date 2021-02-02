<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Messenger\TaskMessage;
use SchedulerBundle\Messenger\TaskMessageHandler;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @group time-sensitive
 */
final class TaskMessageHandlerTest extends TestCase
{
    public function testHandlerCanRunDueTask(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->setScheduledAt(new DateTimeImmutable());
        $task->setExpression('* * * * *');
        $task->setTimezone(new DateTimeZone('UTC'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(false);
        $worker->expects(self::once())->method('execute')->with([], $task);

        $handler = new TaskMessageHandler($worker);

        ($handler)(new TaskMessage($task));
    }

    public function testHandlerCanWaitForAvailableWorker(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->setScheduledAt(new DateTimeImmutable());
        $task->setExpression('* * * * *');
        $task->setTimezone(new DateTimeZone('UTC'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::exactly(3))->method('isRunning')->willReturnOnConsecutiveCalls(true, true, false);
        $worker->expects(self::once())->method('execute')->with([], $task);

        $handler = new TaskMessageHandler($worker);

        ($handler)(new TaskMessage($task));
    }
}
