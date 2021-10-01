<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Messenger\TaskToUpdateMessage;
use SchedulerBundle\Messenger\TaskToUpdateMessageHandler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToUpdateMessageHandlerTest extends TestCase
{
    public function testHandlerCanYieldTask(): void
    {
        $task = new NullTask('foo');

        $taskToUpdateMessage = new TaskToUpdateMessage('foo', $task);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('update')->with(self::equalTo('foo'), self::equalTo($task));

        $taskToPauseMessageHandler = new TaskToUpdateMessageHandler($scheduler);
        ($taskToPauseMessageHandler)($taskToUpdateMessage);
    }
}
