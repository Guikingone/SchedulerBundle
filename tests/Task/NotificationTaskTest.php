<?php

declare(strict_types=1);

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

        self::assertSame('foo', $task->getName());
        self::assertSame($notification, $task->getNotification());
        self::assertNotEmpty($task->getRecipients());
    }

    public function testTaskCanBeCreatedAndNotificationChangedLater(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new NotificationTask('foo', $notification, $recipient);

        self::assertSame('foo', $task->getName());
        self::assertSame($notification, $task->getNotification());
        self::assertNotEmpty($task->getRecipients());

        $secondNotification = $this->createMock(Notification::class);

        $task->setNotification($secondNotification);
        self::assertSame($secondNotification, $task->getNotification());
    }

    public function testTaskCanBeCreatedAndRecipientsChangedLater(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new NotificationTask('foo', $notification, $recipient);

        self::assertSame('foo', $task->getName());
        self::assertSame($notification, $task->getNotification());
        self::assertNotEmpty($task->getRecipients());

        $secondRecipient = $this->createMock(Recipient::class);

        $task->setRecipients($recipient, $secondRecipient);
        self::assertCount(2, $task->getRecipients());
    }

    public function testTaskCanBeCreatedAndRecipientAddedLater(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new NotificationTask('foo', $notification, $recipient);

        self::assertSame('foo', $task->getName());
        self::assertSame($notification, $task->getNotification());
        self::assertNotEmpty($task->getRecipients());

        $secondRecipient = $this->createMock(Recipient::class);

        $task->addRecipient($secondRecipient);
        self::assertCount(2, $task->getRecipients());
    }
}
