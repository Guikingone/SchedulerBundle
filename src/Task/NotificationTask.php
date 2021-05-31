<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use SchedulerBundle\Exception\RuntimeException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTask extends AbstractTask
{
    public function __construct(string $name, Notification $notification, Recipient ...$recipients)
    {
        $this->defineOptions([
            'notification' => $notification,
            'recipients' => $recipients,
        ], [
            'notification' => Notification::class,
            'recipients' => ['Symfony\Component\Notifier\Recipient\Recipient[]', Recipient::class],
        ]);

        parent::__construct($name);
    }

    public function getNotification(): Notification
    {
        if (!$this->options['notification'] instanceof Notification) {
            throw new RuntimeException('The notification is not set');
        }

        return $this->options['notification'];
    }

    public function setNotification(Notification $notification): self
    {
        $this->options['notification'] = $notification;

        return $this;
    }

    /**
     * @return Recipient[]|Recipient|null
     */
    public function getRecipients()
    {
        return $this->options['recipients'];
    }

    public function addRecipient(Recipient $recipient): self
    {
        $this->options['recipients'][] = $recipient;

        return $this;
    }

    public function setRecipients(Recipient ...$recipients): self
    {
        $this->options['recipients'] = $recipients;

        return $this;
    }
}
