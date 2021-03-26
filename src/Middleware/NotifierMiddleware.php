<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use function is_null;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotifierMiddleware implements PreSchedulingMiddlewareInterface, PostSchedulingMiddlewareInterface, PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface, OrderedMiddlewareInterface
{
    private ?NotifierInterface $notifier;

    public function __construct(?NotifierInterface $notifier = null)
    {
        $this->notifier = $notifier;
    }

    /**
     * {@inheritdoc}
     */
    public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        if (!$task->getBeforeSchedulingNotificationBag() instanceof NotificationTaskBag) {
            return;
        }

        $bag = $task->getBeforeSchedulingNotificationBag();
        $this->notify($bag->getNotification(), $bag->getRecipients());
    }

    /**
     * {@inheritdoc}
     */
    public function postScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        if (!$task->getAfterSchedulingNotificationBag() instanceof NotificationTaskBag) {
            return;
        }

        $bag = $task->getAfterSchedulingNotificationBag();
        $this->notify($bag->getNotification(), $bag->getRecipients());
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
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 2;
    }

    /**
     * @param Recipient[]  $recipients
     */
    private function notify(Notification $notification, array $recipients): void
    {
        if (is_null($this->notifier)) {
            return;
        }

        $this->notifier->send($notification, ...$recipients);
    }
}
