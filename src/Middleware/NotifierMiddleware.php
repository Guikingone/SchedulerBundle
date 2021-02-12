<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotifierMiddleware implements PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface
{
    private ?NotifierInterface $notifier;

    public function __construct(?NotifierInterface $notifier = null)
    {
        $this->notifier = $notifier;
    }

    public function preExecute(TaskInterface $task): void
    {
        if (!$task->getBeforeExecutingNotificationBag() instanceof NotificationTaskBag) {
            return;
        }

        $bag = $task->getBeforeExecutingNotificationBag();
        $this->notify($bag->getNotification(), $bag->getRecipients());
    }

    public function postExecute(TaskInterface $task): void
    {
        if (!$task->getAfterExecutingNotificationBag() instanceof NotificationTaskBag) {
            return;
        }

        $bag = $task->getAfterExecutingNotificationBag();
        $this->notify($bag->getNotification(), $bag->getRecipients());
    }

    /**
     * @param Notification $notification
     * @param Recipient[]  $recipients
     */
    private function notify(Notification $notification, array $recipients): void
    {
        if (null === $this->notifier) {
            return;
        }

        $this->notifier->send($notification, ...$recipients);
    }
}
