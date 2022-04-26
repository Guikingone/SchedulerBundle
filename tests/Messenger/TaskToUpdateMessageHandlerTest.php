<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Messenger\TaskToUpdateMessage;
use SchedulerBundle\Messenger\TaskToUpdateMessageHandler;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToUpdateMessageHandlerTest extends TestCase
{
    public function testHandlerCanUpdateTask(): void
    {
        $task = new NullTask('foo');

        $transport = new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create($task);
        self::assertSame('* * * * *', $transport->list()->get('foo')->getExpression());

        $taskToUpdateMessage = new TaskToUpdateMessage('foo', new NullTask('foo', [
            'expression' => '0 * * * *',
        ]));

        $taskToPauseMessageHandler = new TaskToUpdateMessageHandler($transport);

        ($taskToPauseMessageHandler)($taskToUpdateMessage);

        self::assertCount(1, $transport->list());
        self::assertSame('0 * * * *', $transport->list()->get('foo')->getExpression());
    }
}
