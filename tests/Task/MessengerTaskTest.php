<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

        static::assertInstanceOf(FooMessage::class, $task->getMessage());
    }

    public function testTaskCanBeCreatedAndMessageChangedLater(): void
    {
        $task = new MessengerTask('foo', new FooMessage());
        static::assertInstanceOf(FooMessage::class, $task->getMessage());

        $task->setMessage(new BarMessage());
        static::assertInstanceOf(BarMessage::class, $task->getMessage());
    }
}

final class FooMessage
{
}

final class BarMessage
{
}
