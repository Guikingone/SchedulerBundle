<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\EventListener\ProbeSubscriber;
use SchedulerBundle\Probe\Probe;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeSubscriberTest extends TestCase
{
    public function testSubscriberListenEvents(): void
    {
        self::assertArrayHasKey(TaskScheduledEvent::class, ProbeSubscriber::getSubscribedEvents());
        self::assertSame('onTaskScheduled', ProbeSubscriber::getSubscribedEvents()[TaskScheduledEvent::class]);
        self::assertArrayHasKey(TaskFailedEvent::class, ProbeSubscriber::getSubscribedEvents());
        self::assertSame('onTaskFailed', ProbeSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);
        self::assertArrayHasKey(TaskExecutedEvent::class, ProbeSubscriber::getSubscribedEvents());
        self::assertSame('onTaskExecuted', ProbeSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);
    }

    public function testSubscriberCanTriggerProbeOnScheduledTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $probe = new Probe();

        $subscriber = new ProbeSubscriber($probe);
        $subscriber->onTaskScheduled(new TaskScheduledEvent($task));

        self::assertNotEmpty($probe->getScheduledTasks());
        self::assertSame(1, $probe->getScheduledTasks()->count());
    }

    public function testSubscriberCanTriggerProbeOnFailedTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $probe = new Probe();

        $subscriber = new ProbeSubscriber($probe);
        $subscriber->onTaskFailed(new TaskFailedEvent(new FailedTask($task, 'foo')));

        self::assertNotEmpty($probe->getFailedTasks());
        self::assertSame(1, $probe->getFailedTasks()->count());
    }

    public function testSubscriberCanTriggerProbeOnExecutedTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $probe = new Probe();

        $subscriber = new ProbeSubscriber($probe);
        $subscriber->onTaskExecuted(new TaskExecutedEvent($task));

        self::assertNotEmpty($probe->getExecutedTasks());
        self::assertSame(1, $probe->getExecutedTasks()->count());
    }
}
