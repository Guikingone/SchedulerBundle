<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\EventListener\EmailTaskLifecycleSubscriber;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EmailTaskLifecycleSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        self::assertCount(2, EmailTaskLifecycleSubscriber::getSubscribedEvents());

        self::assertArrayHasKey(TaskExecutedEvent::class, EmailTaskLifecycleSubscriber::getSubscribedEvents());
        self::assertSame([
            'onTaskExecuted',
        ], EmailTaskLifecycleSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);

        self::assertArrayHasKey(TaskFailedEvent::class, EmailTaskLifecycleSubscriber::getSubscribedEvents());
        self::assertSame([
            'onTaskFailed',
        ], EmailTaskLifecycleSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);
    }

    public function testSubscriberCanListenTaskFailure(): void
    {
    }

    public function testSubscriberCanListenTaskExecution(): void
    {
    }
}
