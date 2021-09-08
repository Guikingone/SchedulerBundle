<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Worker\WorkerConfiguration;
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
use function json_decode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskSubscriberTest extends TestCase
{
    public function testEventsAreCorrectlyListened(): void
    {
        self::assertArrayHasKey(KernelEvents::REQUEST, TaskSubscriber::getSubscribedEvents());
        self::assertIsArray(TaskSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
        self::assertContainsEquals('onKernelRequest', TaskSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
        self::assertContainsEquals(50, TaskSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
    }

    public function testInvalidPathCannotBeHandledWithInvalidPath(): void
    {
        $serializer = $this->createMock(Serializer::class);
        $eventSubscriber = $this->createMock(EventDispatcher::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_foo');
        $requestEvent = new RequestEvent($kernel, $request, null);

        $taskSubscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);

        $expected = $request->attributes->all();
        $taskSubscriber->onKernelRequest($requestEvent);

        self::assertSame($expected, $request->attributes->all());
    }

    public function testValidPathCannotBeHandledWithoutParams(): void
    {
        $serializer = $this->createMock(Serializer::class);
        $eventSubscriber = $this->createMock(EventDispatcher::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks');
        $requestEvent = new RequestEvent($kernel, $request, null);

        $taskSubscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('A GET request should at least contain a task name or its expression!');
        self::expectExceptionCode(0);
        $taskSubscriber->onKernelRequest($requestEvent);
    }

    public function testValidPathCanBeHandledWithValidName(): void
    {
        $serializer = $this->createMock(Serializer::class);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTaskLimitSubscriber(1, null));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('app.bar');

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $taskList = new TaskList([$task, $secondTask]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->with(self::equalTo(WorkerConfiguration::create()), self::equalTo($task));

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?name=app.bar');
        $requestEvent = new RequestEvent($kernel, $request, null);

        $taskSubscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);
        $taskSubscriber->onKernelRequest($requestEvent);

        self::assertArrayHasKey('task_filter', $request->attributes->all());
        self::assertSame('app.bar', $request->attributes->get('task_filter'));
    }

    public function testValidPathCanBeHandledWithValidExpression(): void
    {
        $serializer = $this->createMock(Serializer::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('app.bar');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('foo');
        $secondTask->expects(self::once())->method('getExpression')->willReturn('@reboot');

        $taskList = new TaskList([$task, $secondTask]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?expression=* * * * *');
        $requestEvent = new RequestEvent($kernel, $request, null);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTaskLimitSubscriber(1, null));

        $taskSubscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);
        $taskSubscriber->onKernelRequest($requestEvent);

        self::assertArrayHasKey('task_filter', $request->attributes->all());
        self::assertSame('* * * * *', $request->attributes->get('task_filter'));
    }

    public function testResponseIsSetWhenWorkerErrorIsThrown(): void
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
        $worker->expects(self::once())->method('execute')->willThrowException(new RuntimeException('An error occur'));

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?expression=* * * * *');
        $requestEvent = new RequestEvent($kernel, $request, null);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber');

        $taskSubscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);
        $taskSubscriber->onKernelRequest($requestEvent);

        self::assertTrue($requestEvent->hasResponse());

        $response = $requestEvent->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertArrayHasKey('code', json_decode($content, true, 512, JSON_THROW_ON_ERROR));
        self::assertArrayHasKey('message', json_decode($content, true, 512, JSON_THROW_ON_ERROR));
        self::assertArrayHasKey('trace', json_decode($content, true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
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
        $requestEvent = new RequestEvent($kernel, $request, null);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTaskLimitSubscriber(1, $logger));

        $serializer = $this->createMock(Serializer::class);
        $serializer->expects(self::once())->method('normalize')->with([$task], self::equalTo('json'))->willReturn([]);

        $taskSubscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer, $logger);
        $taskSubscriber->onKernelRequest($requestEvent);

        self::assertTrue($requestEvent->hasResponse());

        $response = $requestEvent->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertArrayHasKey('code', json_decode($content, true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(Response::HTTP_OK, json_decode($content, true, 512, JSON_THROW_ON_ERROR)['code']);
        self::assertArrayHasKey('tasks', json_decode($content, true, 512, JSON_THROW_ON_ERROR));
        self::assertEmpty(json_decode($content, true, 512, JSON_THROW_ON_ERROR)['tasks']);
    }
}
