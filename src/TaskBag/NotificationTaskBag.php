<?php

declare(strict_types=1);

namespace SchedulerBundle\TaskBag;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTaskBag implements TaskBagInterface
{
    private Notification $notification;

    /**
     * @var Recipient[]
     */
    private array $recipients;

    public function __construct(
        Notification $notification,
        Recipient ...$recipients
    ) {
        $this->notification = $notification;
        $this->recipients = $recipients;
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }

    /**
     * @return Recipient[]
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }
}
