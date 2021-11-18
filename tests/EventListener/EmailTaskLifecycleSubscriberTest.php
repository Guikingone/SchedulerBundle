<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\EventListener\EmailTaskLifecycleSubscriber;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Trigger\EmailTriggerConfiguration;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EmailTaskLifecycleSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        self::assertCount(2, EmailTaskLifecycleSubscriber::getSubscribedEvents());

        self::assertArrayHasKey(TaskExecutedEvent::class, EmailTaskLifecycleSubscriber::getSubscribedEvents());
        self::assertSame('onTaskExecuted', EmailTaskLifecycleSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);

        self::assertArrayHasKey(TaskFailedEvent::class, EmailTaskLifecycleSubscriber::getSubscribedEvents());
        self::assertSame('onTaskFailed', EmailTaskLifecycleSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);
    }

    public function testSubscriberCanListenTaskFailureWithoutSendingAnEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $task = new NullTask('foo');

        $configuration = new EmailTriggerConfiguration(true, 2, 2, 'foo', 'bar');

        $subscriber = new EmailTaskLifecycleSubscriber($configuration, $mailer);

        $subscriber->onTaskFailed(new FailedTask($task, 'why not'));
        $subscriber->onTaskExecuted(new TaskExecutedEvent($task, new Output($task)));
    }

    public function testSubscriberCanListenTaskFailureThenSendingAnEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $task = new NullTask('foo');
        $configuration = new EmailTriggerConfiguration(true, 1, 1, 'foo', 'bar');

        $subscriber = new EmailTaskLifecycleSubscriber($configuration, $mailer);

        $subscriber->onTaskFailed(new FailedTask($task, 'why not'));
        $subscriber->onTaskExecuted(new TaskExecutedEvent($task, new Output($task)));
    }

    public function testSubscriberCanListenTaskExecution(): void
    {
    }
}
