<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Messenger;

use PHPUnit\Framework\TestCase;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToPauseMessageTest extends TestCase
{
    public function testTaskCanBeRetrieved(): void
    {
        $message = new TaskToPauseMessageTest('foo');

        self::assertSame('foo', $message->getName());
    }
}
