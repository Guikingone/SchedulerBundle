<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\TaskBag;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTaskBagTest extends TestCase
{
    public function testBagReturnData(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTaskBag = new NotificationTaskBag($notification, $recipient);

        self::assertSame($notification, $notificationTaskBag->getNotification());
        self::assertContains($recipient, $notificationTaskBag->getRecipients());
    }
}
