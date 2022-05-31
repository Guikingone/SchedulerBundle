<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SchedulerBundle\EventListener\StopWorkerOnTaskLimitSubscriber;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\NullTask;
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
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
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
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer,
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTaskLimitSubscriber(1, null));

        $task = new NullTask('app.bar');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([
            $task,
            new NullTask('foo'),
        ]));

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
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer,
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([
            new NullTask('app.bar'),
            new NullTask('foo', [
                'expression' => '@reboot',
            ]),
        ]));

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
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer,
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

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
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertArrayHasKey('code', json_decode($content, true, 512, JSON_THROW_ON_ERROR));
        self::assertArrayHasKey('message', json_decode($content, true, 512, JSON_THROW_ON_ERROR));
        self::assertArrayHasKey('trace', json_decode($content, true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    public function testResponseIsSetWhenWorkerSucceed(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer,
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $logger = $this->createMock(LoggerInterface::class);

        $task = new NullTask('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([
            $task,
        ]));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('execute');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://www.foo.com/_tasks?expression=* * * * *');
        $requestEvent = new RequestEvent($kernel, $request, null);

        $eventSubscriber = $this->createMock(EventDispatcher::class);
        $eventSubscriber->expects(self::once())->method('addSubscriber')->with(new StopWorkerOnTaskLimitSubscriber(1, $logger));

        $taskSubscriber = new TaskSubscriber($scheduler, $worker, $eventSubscriber, $serializer, $logger);
        $taskSubscriber->onKernelRequest($requestEvent);

        self::assertTrue($requestEvent->hasResponse());

        $response = $requestEvent->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertCount(1, json_decode($content, true, 512, JSON_THROW_ON_ERROR));
    }
}
