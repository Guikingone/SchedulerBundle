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
        $task = new MessengerTask('foo', new FooMessage());

        self::assertInstanceOf(FooMessage::class, $task->getMessage());
    }

    public function testTaskCanBeCreatedAndMessageChangedLater(): void
    {
        $task = new MessengerTask('foo', new FooMessage());
        self::assertInstanceOf(FooMessage::class, $task->getMessage());

        $task->setMessage(new BarMessage());
        self::assertInstanceOf(BarMessage::class, $task->getMessage());
    }
}

final class FooMessage
{
}

final class BarMessage
{
}
