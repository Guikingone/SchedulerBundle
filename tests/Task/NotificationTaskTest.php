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
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;
use SchedulerBundle\Task\NotificationTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTaskTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new NotificationTask('foo', $notification, $recipient);

        static::assertSame('foo', $task->getName());
        static::assertSame($notification, $task->getNotification());
        static::assertNotEmpty($task->getRecipients());
    }

    public function testTaskCanBeCreatedAndNotificationChangedLater(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new NotificationTask('foo', $notification, $recipient);

        static::assertSame('foo', $task->getName());
        static::assertSame($notification, $task->getNotification());
        static::assertNotEmpty($task->getRecipients());

        $secondNotification = $this->createMock(Notification::class);

        $task->setNotification($secondNotification);
        static::assertSame($secondNotification, $task->getNotification());
    }

    public function testTaskCanBeCreatedAndRecipientsChangedLater(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new NotificationTask('foo', $notification, $recipient);

        static::assertSame('foo', $task->getName());
        static::assertSame($notification, $task->getNotification());
        static::assertNotEmpty($task->getRecipients());

        $secondRecipient = $this->createMock(Recipient::class);

        $task->setRecipients($recipient, $secondRecipient);
        static::assertCount(2, $task->getRecipients());
    }

    public function testTaskCanBeCreatedAndRecipientAddedLater(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new NotificationTask('foo', $notification, $recipient);

        static::assertSame('foo', $task->getName());
        static::assertSame($notification, $task->getNotification());
        static::assertNotEmpty($task->getRecipients());

        $secondRecipient = $this->createMock(Recipient::class);

        $task->addRecipient($secondRecipient);
        static::assertCount(2, $task->getRecipients());
    }
}
