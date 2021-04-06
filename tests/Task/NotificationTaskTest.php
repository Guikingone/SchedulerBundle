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

        $notificationTask = new NotificationTask('foo', $notification, $recipient);

        self::assertSame('foo', $notificationTask->getName());
        self::assertSame($notification, $notificationTask->getNotification());
        self::assertNotEmpty($notificationTask->getRecipients());
    }

    public function testTaskCanBeCreatedAndNotificationChangedLater(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTask = new NotificationTask('foo', $notification, $recipient);

        self::assertSame('foo', $notificationTask->getName());
        self::assertSame($notification, $notificationTask->getNotification());
        self::assertNotEmpty($notificationTask->getRecipients());

        $secondNotification = $this->createMock(Notification::class);

        $notificationTask->setNotification($secondNotification);
        self::assertSame($secondNotification, $notificationTask->getNotification());
    }

    public function testTaskCanBeCreatedAndRecipientsChangedLater(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTask = new NotificationTask('foo', $notification, $recipient);

        self::assertSame('foo', $notificationTask->getName());
        self::assertSame($notification, $notificationTask->getNotification());
        self::assertNotEmpty($notificationTask->getRecipients());

        $secondRecipient = $this->createMock(Recipient::class);

        $notificationTask->setRecipients($recipient, $secondRecipient);
        self::assertCount(2, $notificationTask->getRecipients());
    }

    public function testTaskCanBeCreatedAndRecipientAddedLater(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTask = new NotificationTask('foo', $notification, $recipient);

        self::assertSame('foo', $notificationTask->getName());
        self::assertSame($notification, $notificationTask->getNotification());
        self::assertNotEmpty($notificationTask->getRecipients());

        $secondRecipient = $this->createMock(Recipient::class);

        $notificationTask->addRecipient($secondRecipient);
        self::assertCount(2, $notificationTask->getRecipients());
    }
}
