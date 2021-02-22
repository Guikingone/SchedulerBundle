<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Messenger\TaskToYieldMessage;
use SchedulerBundle\Messenger\TaskToYieldMessageHandler;
use SchedulerBundle\SchedulerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToYieldMessageHandlerTest extends TestCase
{
    public function testHandlerCanYieldTask(): void
    {
        $message = new TaskToYieldMessage('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('yieldTask')->with(self::equalTo('foo'));

        $handler = new TaskToYieldMessageHandler($scheduler);
        ($handler)($message);
    }
}
