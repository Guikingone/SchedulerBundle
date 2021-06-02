<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\EventListener\MercureEventSubscriber;
use SchedulerBundle\Task\NullTask;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MercureEventSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        self::assertArrayHasKey(TaskScheduledEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onTaskScheduled', -255
        ], MercureEventSubscriber::getSubscribedEvents()[TaskScheduledEvent::class]);
        self::assertArrayHasKey(TaskUnscheduledEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onTaskUnscheduled', -255
        ], MercureEventSubscriber::getSubscribedEvents()[TaskUnscheduledEvent::class]);
        self::assertArrayHasKey(TaskExecutedEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onTaskExecuted', -255
        ], MercureEventSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);
        self::assertArrayHasKey(TaskFailedEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onTaskFailed', -255
        ], MercureEventSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);
    }

    public function testHubCanPublishUpdateOnTaskScheduled(): void
    {
        $task = new NullTask('foo');

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->with(self::equalTo($task), self::equalTo('json'));

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onTaskScheduled(new TaskScheduledEvent($task));
    }

    public function testHubCanPublishUpdateOnTaskUnscheduled(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::equalTo(new Update('https://www.hub.com/', json_encode([
            'event' => 'task.unscheduled',
            'body' => [
                'task' => 'foo',
            ],
        ]))));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onTaskUnscheduled(new TaskUnscheduledEvent('foo'));
    }

    public function testHubCanPublishUpdateOnTaskExecuted(): void
    {
    }

    public function testHubCanPublishUpdateOnTaskFailed(): void
    {
    }
}
