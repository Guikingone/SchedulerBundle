<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use DateTimeInterface;
use JsonException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Event\WorkerForkedEvent;
use SchedulerBundle\Event\WorkerPausedEvent;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\EventListener\MercureEventSubscriber;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use function json_encode;
use const JSON_THROW_ON_ERROR;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MercureEventSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        self::assertCount(9, MercureEventSubscriber::getSubscribedEvents());

        self::assertArrayHasKey(TaskScheduledEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onTaskScheduled', -255,
        ], MercureEventSubscriber::getSubscribedEvents()[TaskScheduledEvent::class]);

        self::assertArrayHasKey(TaskUnscheduledEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onTaskUnscheduled', -255,
        ], MercureEventSubscriber::getSubscribedEvents()[TaskUnscheduledEvent::class]);

        self::assertArrayHasKey(TaskExecutedEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onTaskExecuted', -255,
        ], MercureEventSubscriber::getSubscribedEvents()[TaskExecutedEvent::class]);

        self::assertArrayHasKey(TaskFailedEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onTaskFailed', -255,
        ], MercureEventSubscriber::getSubscribedEvents()[TaskFailedEvent::class]);

        self::assertArrayHasKey(WorkerPausedEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onWorkerPaused', -255,
        ], MercureEventSubscriber::getSubscribedEvents()[WorkerPausedEvent::class]);

        self::assertArrayHasKey(WorkerStartedEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onWorkerStarted', -255,
        ], MercureEventSubscriber::getSubscribedEvents()[WorkerStartedEvent::class]);

        self::assertArrayHasKey(WorkerStoppedEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onWorkerStopped', -255,
        ], MercureEventSubscriber::getSubscribedEvents()[WorkerStoppedEvent::class]);

        self::assertArrayHasKey(WorkerForkedEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onWorkerForked', -255,
        ], MercureEventSubscriber::getSubscribedEvents()[WorkerForkedEvent::class]);

        self::assertArrayHasKey(WorkerRestartedEvent::class, MercureEventSubscriber::getSubscribedEvents());
        self::assertSame([
            'onWorkerRestarted', -255,
        ], MercureEventSubscriber::getSubscribedEvents()[WorkerRestartedEvent::class]);
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function testHubCanPublishUpdateOnTaskScheduled(): void
    {
        $task = new NullTask('foo');

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::equalTo(new Update('https://www.hub.com/', json_encode([
            'event' => 'task.scheduled',
            'body' => [
                'task' => 'foo',
            ],
        ], JSON_THROW_ON_ERROR))));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')
            ->with(self::equalTo($task), self::equalTo('json'))
            ->willReturn('foo')
        ;

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onTaskScheduled(new TaskScheduledEvent($task));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function testHubCanPublishUpdateOnTaskUnscheduled(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::equalTo(new Update('https://www.hub.com/', json_encode([
            'event' => 'task.unscheduled',
            'body' => [
                'task' => 'foo',
            ],
        ], JSON_THROW_ON_ERROR))));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onTaskUnscheduled(new TaskUnscheduledEvent('foo'));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function testHubCanPublishUpdateOnTaskExecuted(): void
    {
        $task = new NullTask('foo');

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::equalTo(new Update('https://www.hub.com/', json_encode([
            'event' => 'task.executed',
            'body' => [
                'task' => 'foo',
                'output' => 'undefined',
            ],
        ], JSON_THROW_ON_ERROR))));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')
            ->with(self::equalTo($task), self::equalTo('json'))
            ->willReturn('foo')
        ;

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onTaskExecuted(new TaskExecutedEvent($task, new Output($task)));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function testHubCanPublishUpdateOnTaskFailed(): void
    {
        $task = new NullTask('foo');
        $failedTask = new FailedTask($task, 'why not?');

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::equalTo(new Update('https://www.hub.com/', json_encode([
            'event' => 'task.failed',
            'body' => [
                'task' => 'foo',
                'reason' => 'why not?',
                'failedAt' => $failedTask->getFailedAt()->format(DateTimeInterface::W3C),
            ],
        ], JSON_THROW_ON_ERROR))));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')
            ->with(self::equalTo($task), self::equalTo('json'))
            ->willReturn('foo')
        ;

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onTaskFailed(new TaskFailedEvent($failedTask));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function testHubCanPublishUpdateOnWorkerPaused(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getConfiguration')->willReturn(WorkerConfiguration::create());

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::equalTo(new Update('https://www.hub.com/', json_encode([
            'event' => 'worker.paused',
            'body' => [
                'options' => [
                    'executedTasksCount' => 0,
                    'forkedFrom' => null,
                    'isFork' => false,
                    'isRunning' => false,
                    'lastExecutedTask' => null,
                    'sleepDurationDelay' => 1,
                    'sleepUntilNextMinute' => false,
                    'shouldStop' => false,
                    'shouldRetrieveTasksLazily' => false,
                    'mustStrictlyCheckDate' => false,
                ],
            ],
        ], JSON_THROW_ON_ERROR))));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onWorkerPaused(new WorkerPausedEvent($worker));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function testHubCanPublishUpdateOnWorkerStarted(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getConfiguration')->willReturn(WorkerConfiguration::create());

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::equalTo(new Update('https://www.hub.com/', json_encode([
            'event' => 'worker.started',
            'body' => [
                'options' => [
                    'executedTasksCount' => 0,
                    'forkedFrom' => null,
                    'isFork' => false,
                    'isRunning' => false,
                    'lastExecutedTask' => null,
                    'sleepDurationDelay' => 1,
                    'sleepUntilNextMinute' => false,
                    'shouldStop' => false,
                    'shouldRetrieveTasksLazily' => false,
                    'mustStrictlyCheckDate' => false,
                ],
            ],
        ], JSON_THROW_ON_ERROR))));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onWorkerStarted(new WorkerStartedEvent($worker));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function testHubCanPublishUpdateOnWorkerStopped(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getConfiguration')->willReturn(WorkerConfiguration::create());
        $worker->expects(self::once())->method('getLastExecutedTask')->willReturn(null);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::equalTo(new Update('https://www.hub.com/', json_encode([
            'event' => 'worker.stopped',
            'body' => [
                'lastExecutedTask' => 'foo',
                'options' => [
                    'executedTasksCount' => 0,
                    'forkedFrom' => null,
                    'isFork' => false,
                    'isRunning' => false,
                    'lastExecutedTask' => null,
                    'sleepDurationDelay' => 1,
                    'sleepUntilNextMinute' => false,
                    'shouldStop' => false,
                    'shouldRetrieveTasksLazily' => false,
                    'mustStrictlyCheckDate' => false,
                ],
            ],
        ], JSON_THROW_ON_ERROR))));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->willReturn('foo');

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onWorkerStopped(new WorkerStoppedEvent($worker));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function testHubCanPublishUpdateOnWorkerForked(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getConfiguration')->willReturn(WorkerConfiguration::create());
        $worker->expects(self::never())->method('getLastExecutedTask');

        $secondWorker = $this->createMock(WorkerInterface::class);
        $secondWorker->expects(self::once())->method('getConfiguration')->willReturn(WorkerConfiguration::create());
        $secondWorker->expects(self::never())->method('getLastExecutedTask');

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::equalTo(new Update('https://www.hub.com/', json_encode([
            'event' => 'worker.forked',
            'body' => [
                'oldWorkerOptions' => [
                    'executedTasksCount' => 0,
                    'forkedFrom' => null,
                    'isFork' => false,
                    'isRunning' => false,
                    'lastExecutedTask' => null,
                    'sleepDurationDelay' => 1,
                    'sleepUntilNextMinute' => false,
                    'shouldStop' => false,
                    'shouldRetrieveTasksLazily' => false,
                    'mustStrictlyCheckDate' => false,
                ],
                'forkedWorkerOptions' => [
                    'executedTasksCount' => 0,
                    'forkedFrom' => null,
                    'isFork' => false,
                    'isRunning' => false,
                    'lastExecutedTask' => null,
                    'sleepDurationDelay' => 1,
                    'sleepUntilNextMinute' => false,
                    'shouldStop' => false,
                    'shouldRetrieveTasksLazily' => false,
                    'mustStrictlyCheckDate' => false,
                ],
            ],
        ], JSON_THROW_ON_ERROR))));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onWorkerForked(new WorkerForkedEvent($worker, $secondWorker));
    }

    /**
     * @throws JsonException {@see json_encode()}
     */
    public function testHubCanPublishUpdateOnWorkerRestarted(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getConfiguration')->willReturn(WorkerConfiguration::create());
        $worker->expects(self::once())->method('getLastExecutedTask')->willReturn(null);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::equalTo(new Update('https://www.hub.com/', json_encode([
            'event' => 'worker.restarted',
            'body' => [
                'lastExecutedTask' => 'foo',
                'options' => [
                    'executedTasksCount' => 0,
                    'forkedFrom' => null,
                    'isFork' => false,
                    'isRunning' => false,
                    'lastExecutedTask' => null,
                    'sleepDurationDelay' => 1,
                    'sleepUntilNextMinute' => false,
                    'shouldStop' => false,
                    'shouldRetrieveTasksLazily' => false,
                    'mustStrictlyCheckDate' => false,
                ],
            ],
        ], JSON_THROW_ON_ERROR))));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->willReturn('foo');

        $subscriber = new MercureEventSubscriber($hub, 'https://www.hub.com/', $serializer);
        $subscriber->onWorkerRestarted(new WorkerRestartedEvent($worker));
    }
}
