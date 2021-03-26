<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Messenger\TaskToYieldMessage;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToYieldMessageTest extends TestCase
{
    public function testTaskCanBeRetrieved(): void
    {
        $taskToYieldMessage = new TaskToYieldMessage('foo');

        self::assertSame('foo', $taskToYieldMessage->getName());
    }
}
