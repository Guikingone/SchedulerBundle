<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\MessengerTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerTaskTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $messengerTask = new MessengerTask('foo', new FooMessage());

        self::assertInstanceOf(FooMessage::class, $messengerTask->getMessage());
    }

    public function testTaskCanBeCreatedAndMessageChangedLater(): void
    {
        $messengerTask = new MessengerTask('foo', new FooMessage());
        self::assertInstanceOf(FooMessage::class, $messengerTask->getMessage());

        $messengerTask->setMessage(new BarMessage());
        self::assertInstanceOf(BarMessage::class, $messengerTask->getMessage());
    }
}

final class FooMessage
{
}

final class BarMessage
{
}
