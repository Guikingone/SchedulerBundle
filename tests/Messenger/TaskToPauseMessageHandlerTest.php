<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Messenger\TaskToPauseMessage;
use SchedulerBundle\Messenger\TaskToPauseMessageHandler;
use SchedulerBundle\SchedulerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToPauseMessageHandlerTest extends TestCase
{
    public function testHandlerCanYieldTask(): void
    {
        $taskToPauseMessage = new TaskToPauseMessage('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('pause')->with(self::equalTo('foo'));

        $taskToPauseMessageHandler = new TaskToPauseMessageHandler($scheduler);
        ($taskToPauseMessageHandler)($taskToPauseMessage);
    }
}
