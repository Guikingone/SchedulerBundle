<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use DateTimeImmutable;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use function json_encode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MercureEventSubscriber implements EventSubscriberInterface
{
    private HubInterface $hub;
    private string $updateUrl;
    private SerializerInterface $serializer;

    public function __construct(
        HubInterface $hub,
        string $updateUrl,
        SerializerInterface $serializer
    ) {
        $this->hub = $hub;
        $this->updateUrl = $updateUrl;
        $this->serializer = $serializer;
    }

    public function onTaskScheduled(TaskScheduledEvent $event): void
    {
        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'task.scheduled',
            'body' => $this->serializer->serialize($event->getTask(), 'json'),
        ])));
    }

    public function onTaskUnscheduled(TaskUnscheduledEvent $event): void
    {
        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'task.unscheduled',
            'body' => $this->serializer->serialize($event->getTask(), 'json'),
        ])));
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'task.executed',
            'body' => [
                'task' => $this->serializer->serialize($event->getTask(), 'json'),
                'output' => $event->getOutput(),
            ],
        ])));
    }

    public function onTaskFailed(TaskFailedEvent $event): void
    {
        $failedTask = $event->getTask();

        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'task.executed.errored',
            'body' => [
                'task' => $this->serializer->serialize($failedTask->getTask(), 'json'),
                'reason' => $failedTask->getReason(),
                'failedAt' => $failedTask->getFailedAt()->format(DateTimeImmutable::W3C),
            ],
        ])));
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskScheduledEvent::class => 'onTaskScheduled',
            TaskUnscheduledEvent::class => 'onTaskUnscheduled',
            TaskExecutedEvent::class => 'onTaskExecuted',
            TaskFailedEvent::class => 'onTaskFailed',
        ];
    }
}
