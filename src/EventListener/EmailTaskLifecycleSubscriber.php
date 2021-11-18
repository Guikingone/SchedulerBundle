<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Trigger\EmailTriggerConfiguration;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EmailTaskLifecycleSubscriber implements EventSubscriberInterface
{
    private TaskListInterface $failedTasksList;
    private TaskListInterface $succeedTasksList;
    private ?MailerInterface $mailer;
    private EmailTriggerConfiguration $emailTriggerConfiguration;

    public function __construct(
        EmailTriggerConfiguration $emailTriggerConfiguration,
        ?MailerInterface $mailer = null
    ) {
        $this->emailTriggerConfiguration = $emailTriggerConfiguration;
        $this->mailer = $mailer;

        $this->failedTasksList = new TaskList();
        $this->succeedTasksList = new TaskList();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskExecutedEvent::class => 'onTaskExecuted',
            TaskFailedEvent::class => 'onTaskFailed',
        ];
    }

    public function onTaskFailed(FailedTask $failedTask): void
    {
        $this->failedTasksList->add($failedTask->getTask());
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $task = $event->getTask();
        $output = $event->getOutput();

        $this->handleTaskFailure($task, $output);
        $this->handleTaskSuccess($task, $output);
    }

    private function handleTaskFailure(TaskInterface $task, Output $output): void
    {
        if ($this->failedTasksList->count() !== $this->emailTriggerConfiguration->getFailureTriggeredAt()) {
            return;
        }

        $this->send(
            (new TemplatedEmail())
            ->from($this->emailTriggerConfiguration->getFailureFrom())
            ->to(new Address($this->emailTriggerConfiguration->getFailureTo()))
            ->subject($this->emailTriggerConfiguration->getFailureSubject())
            ->htmlTemplate('emails/task_failure.html.twig')
            ->context([
                'tasks' => $this->failedTasksList->toArray(false),
            ])
        );
    }

    private function handleTaskSuccess(TaskInterface $task, Output $output): void
    {
        if ($task->getExecutionState() !== TaskInterface::SUCCEED) {
            return;
        }

        $this->succeedTasksList->add($task);

        if ($this->succeedTasksList->count() !== $this->emailTriggerConfiguration->getSuccessTriggeredAt()) {
            return;
        }

        $this->send(
            (new TemplatedEmail())
            ->from($this->emailTriggerConfiguration->getSuccessFrom())
            ->to(new Address($this->emailTriggerConfiguration->getSuccessTo()))
            ->subject($this->emailTriggerConfiguration->getFailureSubject())
            ->htmlTemplate('emails/task_success.html.twig')
            ->context([
                'tasks' => $this->succeedTasksList->toArray(false),
            ])
        );
    }

    private function send(Email $email): void
    {
        if (null === $this->mailer) {
            return;
        }

        $this->mailer->send($email);
    }
}
