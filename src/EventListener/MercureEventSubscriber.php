<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use DateTimeImmutable;
use JsonException;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Event\WorkerForkedEvent;
use SchedulerBundle\Event\WorkerPausedEvent;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use function json_encode;
use const JSON_THROW_ON_ERROR;

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

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function onTaskScheduled(TaskScheduledEvent $event): void
    {
        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'task.scheduled',
            'body' => [
                'task' => $this->serializer->serialize($event->getTask(), 'json'),
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function onTaskUnscheduled(TaskUnscheduledEvent $event): void
    {
        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'task.unscheduled',
            'body' => [
                'task' => $event->getTask(),
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $output = $event->getOutput();

        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'task.executed',
            'body' => [
                'task' => $this->serializer->serialize($event->getTask(), 'json'),
                'output' => $output->getOutput(),
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function onTaskFailed(TaskFailedEvent $event): void
    {
        $failedTask = $event->getTask();

        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'task.failed',
            'body' => [
                'task' => $this->serializer->serialize($failedTask->getTask(), 'json'),
                'reason' => $failedTask->getReason(),
                'failedAt' => $failedTask->getFailedAt()->format(DateTimeImmutable::W3C),
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function onWorkerPaused(WorkerPausedEvent $event): void
    {
        $worker = $event->getWorker();
        $configuration = $worker->getConfiguration();

        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'worker.paused',
            'body' => [
                'options' => $configuration->toArray(),
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $worker = $event->getWorker();
        $configuration = $worker->getConfiguration();

        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'worker.started',
            'body' => [
                'options' => $configuration->toArray(),
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function onWorkerStopped(WorkerStoppedEvent $event): void
    {
        $worker = $event->getWorker();
        $configuration = $worker->getConfiguration();

        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'worker.stopped',
            'body' => [
                'lastExecutedTask' => $this->serializer->serialize($worker->getLastExecutedTask(), 'json'),
                'options' => $configuration->toArray(),
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function onWorkerForked(WorkerForkedEvent $event): void
    {
        $worker = $event->getForkedWorker();
        $configuration = $worker->getConfiguration();

        $forkedWorker = $event->getNewWorker();
        $forkedConfiguration = $forkedWorker->getConfiguration();

        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'worker.forked',
            'body' => [
                'oldWorkerOptions' => $configuration->toArray(),
                'forkedWorkerOptions' => $forkedConfiguration->toArray(),
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function onWorkerRestarted(WorkerRestartedEvent $event): void
    {
        $worker = $event->getWorker();
        $configuration = $worker->getConfiguration();

        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'worker.restarted',
            'body' => [
                'lastExecutedTask' => $this->serializer->serialize($worker->getLastExecutedTask(), 'json'),
                'options' => $configuration->toArray(),
            ],
        ], JSON_THROW_ON_ERROR)));
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskScheduledEvent::class => ['onTaskScheduled', -255],
            TaskUnscheduledEvent::class => ['onTaskUnscheduled', -255],
            TaskExecutedEvent::class => ['onTaskExecuted', -255],
            TaskFailedEvent::class => ['onTaskFailed', -255],
            WorkerPausedEvent::class => ['onWorkerPaused', -255],
            WorkerStartedEvent::class => ['onWorkerStarted', -255],
            WorkerStoppedEvent::class => ['onWorkerStopped', -255],
            WorkerForkedEvent::class => ['onWorkerForked', -255],
            WorkerRestartedEvent::class => ['onWorkerRestarted', -255],
        ];
    }
}
