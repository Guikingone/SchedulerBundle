<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Messenger\TaskToExecuteMessage;
use SchedulerBundle\Messenger\TaskToExecuteMessageHandler;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @group time-sensitive
 */
final class TaskToExecuteMessageHandlerTest extends TestCase
{
    public function testHandlerCanRunDueTaskWithoutASpecificTimezone(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getTimezone')->willReturn(null);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(false);
        $worker->expects(self::once())->method('execute')->with(WorkerConfiguration::create(), $task);

        $taskMessageHandler = new TaskToExecuteMessageHandler($worker);

        ($taskMessageHandler)(new TaskToExecuteMessage($task));
    }

    public function testHandlerCanRunDueTask(): void
    {
        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);
        $shellTask->setScheduledAt(new DateTimeImmutable());
        $shellTask->setExpression('* * * * *');
        $shellTask->setTimezone(new DateTimeZone('UTC'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(false);
        $worker->expects(self::once())->method('execute')->with(WorkerConfiguration::create(), $shellTask);

        $taskMessageHandler = new TaskToExecuteMessageHandler($worker);

        ($taskMessageHandler)(new TaskToExecuteMessage($shellTask));
    }

    public function testHandlerCanWaitForAvailableWorker(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')->with(self::equalTo('The task "foo" cannot be executed for now as the worker is currently running'));

        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);
        $shellTask->setScheduledAt(new DateTimeImmutable());
        $shellTask->setExpression('* * * * *');
        $shellTask->setTimezone(new DateTimeZone('UTC'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::exactly(3))->method('isRunning')->willReturnOnConsecutiveCalls(true, true, false);
        $worker->expects(self::once())->method('execute')->with(WorkerConfiguration::create(), $shellTask);

        $taskMessageHandler = new TaskToExecuteMessageHandler($worker, $logger);

        ($taskMessageHandler)(new TaskToExecuteMessage($shellTask, 2));
    }
}
