<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\EventListener\ProbeStateSubscriber;
use SchedulerBundle\Probe\Probe;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeStateSubscriberTest extends TestCase
{
    public function testEventsAreCorrectlyListened(): void
    {
        self::assertArrayHasKey(KernelEvents::REQUEST, ProbeStateSubscriber::getSubscribedEvents());
        self::assertContainsEquals('onKernelRequest', ProbeStateSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
        self::assertContainsEquals(50, ProbeStateSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0]);
    }

    public function testSubscriberCannotBeUsedOnWrongPath(): void
    {
        $kernel = $this->createMock(KernelInterface::class);

        $probe = new Probe();
        $event = new RequestEvent($kernel, Request::create('/_foo'), HttpKernelInterface::MASTER_REQUEST);

        $subscriber = new ProbeStateSubscriber($probe);
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSubscriberCannotBeUsedOnWrongMethod(): void
    {
        $kernel = $this->createMock(KernelInterface::class);

        $probe = new Probe();
        $event = new RequestEvent($kernel, Request::create('/_probe', 'POST'), HttpKernelInterface::MASTER_REQUEST);

        $subscriber = new ProbeStateSubscriber($probe);
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSubscriberCanBeUsed(): void
    {
        $kernel = $this->createMock(KernelInterface::class);

        $probe = new Probe();
        $event = new RequestEvent($kernel, Request::create('/_probe'), HttpKernelInterface::MASTER_REQUEST);

        $subscriber = new ProbeStateSubscriber($probe);
        $subscriber->onKernelRequest($event);

        self::assertInstanceOf(JsonResponse::class, $event->getResponse());

        $body = json_decode($event->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('scheduledTasks', $body);
        self::assertSame(0, $body['scheduledTasks']);
        self::assertArrayHasKey('executedTasks', $body);
        self::assertSame(0, $body['executedTasks']);
        self::assertArrayHasKey('failedTasks', $body);
        self::assertSame(0, $body['failedTasks']);
    }
}
