<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

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
        $bag = $task->getBeforeSchedulingNotificationBag();
        if (!$bag instanceof NotificationTaskBag) {
            return;
        }

        $this->notify($bag->getNotification(), $bag->getRecipients());
    }

    /**
     * {@inheritdoc}
     */
    public function postScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $bag = $task->getAfterSchedulingNotificationBag();
        if (!$bag instanceof NotificationTaskBag) {
            return;
        }

        $this->notify($bag->getNotification(), $bag->getRecipients());
    }

    public function preExecute(TaskInterface $task): void
    {
        $bag = $task->getBeforeExecutingNotificationBag();
        if (!$bag instanceof NotificationTaskBag) {
            return;
        }

        $this->notify($bag->getNotification(), $bag->getRecipients());
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
        $bag = $task->getAfterExecutingNotificationBag();
        if (!$bag instanceof NotificationTaskBag) {
            return;
        }

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
        if (!$this->notifier instanceof NotifierInterface) {
            return;
        }

        $this->notifier->send($notification, ...$recipients);
    }
}
