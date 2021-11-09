<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EmailTaskLifecycleSubscriber implements EventSubscriberInterface
{
    private int $taskFailureAmount;
    private int $taskSuccessAmount;
    private ?MailerInterface $mailer;

    public function __construct(
        int $taskFailureAmount,
        int $taskSuccessAmount,
        ?MailerInterface $mailer = null
    ) {
        $this->taskFailureAmount = $taskFailureAmount;
        $this->taskSuccessAmount = $taskSuccessAmount;
        $this->mailer = $mailer;
    }

    public function onTaskFailure(TaskExecutedEvent $event): void
    {
    }

    public function onTaskSuccess(TaskFailedEvent $event): void
    {
    }

    private function send(Email $email): void
    {
        if (null === $this->mailer) {
            return;
        }

        $this->mailer->send($email);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
    }
}
