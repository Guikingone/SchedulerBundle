<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\EventListener\ProbeStateSubscriber;
use SchedulerBundle\Probe\Probe;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;
use function json_decode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeStateSubscriberTest extends TestCase
{
    public function testEventsAreCorrectlyListened(): void
    {
        self::assertArrayHasKey(KernelEvents::REQUEST, ProbeStateSubscriber::getSubscribedEvents());
        self::assertIsArray(ProbeStateSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
        self::assertArrayHasKey(0, ProbeStateSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
        self::assertArrayHasKey(1, ProbeStateSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
        self::assertSame('onKernelRequest', ProbeStateSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0][0]);
        self::assertSame(50, ProbeStateSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0][1]);
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSubscriberCannotBeUsedOnWrongPath(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $probe = new Probe($scheduler, $worker);
        $event = new RequestEvent($kernel, Request::create('/_foo'), null);

        $subscriber = new ProbeStateSubscriber($probe);
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSubscriberCannotBeUsedOnWrongMethod(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $probe = new Probe($scheduler, $worker);
        $event = new RequestEvent($kernel, Request::create('/_probe', 'POST'), null);

        $subscriber = new ProbeStateSubscriber($probe);
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testSubscriberCanBeUsed(): void
    {
        $kernel = $this->createMock(KernelInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::exactly(2))->method('getTasks')->willReturn(new TaskList());

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn(new TaskList());

        $probe = new Probe($scheduler, $worker);
        $event = new RequestEvent($kernel, Request::create('/_probe'), null);

        $subscriber = new ProbeStateSubscriber($probe);
        $subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);

        $content = $response->getContent();
        self::assertIsString($content);

        $body = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('scheduledTasks', $body);
        self::assertSame(0, $body['scheduledTasks']);
        self::assertArrayHasKey('executedTasks', $body);
        self::assertSame(0, $body['executedTasks']);
        self::assertArrayHasKey('failedTasks', $body);
        self::assertSame(0, $body['failedTasks']);
    }
}
