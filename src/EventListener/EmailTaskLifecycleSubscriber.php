<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Trigger\EmailTriggerConfiguration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EmailTaskLifecycleSubscriber implements EventSubscriberInterface
{
    private int $failedTasks;
    private int $succeededTasks;
    private ?MailerInterface $mailer;
    private EmailTriggerConfiguration $emailTriggerConfiguration;

    public function __construct(
        EmailTriggerConfiguration $emailTriggerConfiguration,
        ?MailerInterface $mailer = null
    ) {
        $this->emailTriggerConfiguration = $emailTriggerConfiguration;
        $this->mailer = $mailer;
    }

    public function onTaskFailure(): void
    {
        ++$this->failedTasks;
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $task = $event->getTask();
        $output = $event->getOutput();

        $this->onTaskFailed($task, $output);
        $this->onTaskSuccess($task, $output);
    }

    private function send(Email $email): void
    {
        if (null === $this->mailer) {
            return;
        }

        $this->mailer->send($email);
    }

    private function onTaskFailed(TaskInterface $task, Output $output): void
    {
        if ($this->failedTasks !== $this->emailTriggerConfiguration->getFailureTriggeredAt()) {
            return;
        }

        $this->send();
    }

    private function onTaskSuccess(TaskInterface $task, Output $output): void
    {
        if ($task->getExecutionState() !== TaskInterface::SUCCEED) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskFailedEvent::class => 'onTaskFailure',
        ];
    }
}
