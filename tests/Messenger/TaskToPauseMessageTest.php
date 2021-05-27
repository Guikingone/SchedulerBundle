<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Messenger\TaskToPauseMessage;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToPauseMessageTest extends TestCase
{
    public function testTaskCanBeRetrieved(): void
    {
        $taskToPauseMessage = new TaskToPauseMessage('foo');

        self::assertSame('foo', $taskToPauseMessage->getTask());
    }
}
