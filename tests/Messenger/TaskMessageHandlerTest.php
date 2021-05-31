<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);
        $shellTask->setScheduledAt(new DateTimeImmutable());
        $shellTask->setExpression('* * * * *');
        $shellTask->setTimezone(new DateTimeZone('UTC'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(false);
        $worker->expects(self::once())->method('execute')->with([], $shellTask);

        $taskMessageHandler = new TaskMessageHandler($worker);

        ($taskMessageHandler)(new TaskMessage($shellTask));
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
        $worker->expects(self::once())->method('execute')->with([], $shellTask);

        $taskMessageHandler = new TaskMessageHandler($worker, $logger);

        ($taskMessageHandler)(new TaskMessage($shellTask, 2));
    }
}
