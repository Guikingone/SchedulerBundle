<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTask extends AbstractTask
{
    private Notification $notification;

    /**
     * @var Recipient[]
     */
    private array $recipients;

    public function __construct(string $name, Notification $notification, Recipient ...$recipients)
    {
        $this->notification = $notification;
        $this->recipients = $recipients;

        $this->defineOptions();

        parent::__construct($name);
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }

    public function setNotification(Notification $notification): self
    {
        $this->notification = $notification;

        return $this;
    }

    /**
     * @return Recipient[]
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function addRecipient(Recipient $recipient): self
    {
        $this->recipients[] = $recipient;

        return $this;
    }

    public function setRecipients(Recipient ...$recipients): self
    {
        $this->recipients = $recipients;

        return $this;
    }
}
