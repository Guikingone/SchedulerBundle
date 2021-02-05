<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use SchedulerBundle\EventListener\TaskSubscriber;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function json_decode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskSubscriberTest extends TestCase
{
    public function testEventsAreCorrectlyListened(): void
    {
        self::assertArrayHasKey(KernelEvents::REQUEST, TaskSubscriber::getSubscribedEvents());
        self::assertContainsEquals('onKernelRequest', TaskSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
        self::assertContainsEquals(50, TaskSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
    }

    public function testInvalidPathCannotBeHandledWithInvalidPath(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $eventSubscriber = $this->createMock(EventDispatcherInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_foo');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $subscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);

        $expected = $request->attributes->all();
        $subscriber->onKernelRequest($event);

        self::assertSame($expected, $request->attributes->all());
    }

    public function testValidPathCannotBeHandledWithoutParams(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $eventSubscriber = $this->createMock(EventDispatcherInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $subscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);

        self::expectException(InvalidArgumentException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testValidPathCanBeHandledWithValidName(): void
    {
        $serializer = $this->createMock(Serializer::class);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber');

        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::once())->method('toArray')->willReturn([$task]);
        $taskList->expects(self::once())->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?name=app.bar');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $subscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);
        $subscriber->onKernelRequest($event);

        self::assertArrayHasKey('task_filter', $request->attributes->all());
        self::assertSame('app.bar', $request->attributes->get('task_filter'));
    }

    public function testValidPathCanBeHandledWithValidExpression(): void
    {
        $serializer = $this->createMock(Serializer::class);
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::once())->method('toArray')->willReturn([$task]);
        $taskList->expects(self::once())->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?expression=* * * * *');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber');

        $subscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);
        $subscriber->onKernelRequest($event);

        self::assertArrayHasKey('task_filter', $request->attributes->all());
        self::assertSame('* * * * *', $request->attributes->get('task_filter'));
    }

    public function testResponseIsSetWhenWorkerErrorIsThrown(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::once())->method('toArray')->willReturn([$task]);
        $taskList->expects(self::once())->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->willThrowException(new RuntimeException('An error occur'));

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?expression=* * * * *');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber');

        $subscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);
        $subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertInstanceOf(JsonResponse::class, $event->getResponse());
        self::assertSame(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, $event->getResponse()->getStatusCode());
    }

    public function testResponseIsSetWhenWorkerSucceed(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::once())->method('toArray')->with(self::equalTo(false))->willReturn([$task]);
        $taskList->expects(self::once())->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?expression=* * * * *');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTaskLimitSubscriber(1, $logger));

        $serializer = $this->createMock(Serializer::class);
        $serializer->expects(self::once())->method('normalize')->with([$task], self::equalTo('json'))->willReturn([]);

        $subscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer, $logger);
        $subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertInstanceOf(JsonResponse::class, $event->getResponse());
        self::assertSame(JsonResponse::HTTP_OK, $event->getResponse()->getStatusCode());
        self::assertArrayHasKey('code', json_decode($event->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(Response::HTTP_OK, json_decode($event->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['code']);
        self::assertArrayHasKey('tasks', json_decode($event->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertEmpty(json_decode($event->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['tasks']);
    }
}
