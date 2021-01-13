<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use SchedulerBundle\EventListener\TaskSubscriber;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskSubscriberTest extends TestCase
{
    public function testEventsAreCorrectlyListened(): void
    {
        static::assertArrayHasKey(KernelEvents::REQUEST, TaskSubscriber::getSubscribedEvents());
        static::assertContainsEquals('onKernelRequest', TaskSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
        static::assertContainsEquals(50, TaskSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
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

        static::assertSame($expected, $request->attributes->all());
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

        static::expectException(\InvalidArgumentException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testValidPathCanBeHandledWithValidName(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber');

        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));
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

        static::assertArrayHasKey('task_filter', $request->attributes->all());
        static::assertSame('app.bar', $request->attributes->get('task_filter'));
    }

    public function testValidPathCanBeHandledWithValidExpression(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));
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

        static::assertArrayHasKey('task_filter', $request->attributes->all());
        static::assertSame('* * * * *', $request->attributes->get('task_filter'));
    }

    public function testResponseIsSetWhenWorkerErrorIsThrown(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));
        $taskList->expects(self::once())->method('count')->willReturn(1);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute')->willThrowException(new \RuntimeException('An error occur'));

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?expression=* * * * *');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber');

        $subscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);
        $subscriber->onKernelRequest($event);

        static::assertTrue($event->hasResponse());
        static::assertInstanceOf(JsonResponse::class, $event->getResponse());
        static::assertSame(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, $event->getResponse()->getStatusCode());
    }

    public function testResponseIsSetWhenWorkerSucceed(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('filter')->willReturnSelf();
        $taskList->expects(self::once())->method('getIterator')->willReturn(new \ArrayIterator([$task]));
        $taskList->expects(self::once())->method('count')->willReturn(1);
        $taskList->expects(self::once())->method('toArray')->willReturn(['foo' => $task]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn($taskList);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?expression=* * * * *');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->with(['foo' => $task], 'json');

        $subscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer);
        $subscriber->onKernelRequest($event);

        static::assertTrue($event->hasResponse());
        static::assertInstanceOf(JsonResponse::class, $event->getResponse());
        static::assertSame(JsonResponse::HTTP_OK, $event->getResponse()->getStatusCode());
    }
}
