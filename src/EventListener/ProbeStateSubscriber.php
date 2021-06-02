<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use SchedulerBundle\Probe\ProbeInterface;
use SchedulerBundle\SchedulerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;
use function rawurldecode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeStateSubscriber implements EventSubscriberInterface
{
    private ProbeInterface $probe;
    private string $path;

    public function __construct(ProbeInterface $probe, string $path = '/_probe')
    {
        $this->probe = $probe;
        $this->path = $path;
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($this->path !== rawurldecode($request->getPathInfo())) {
            return;
        }

        if (Request::METHOD_GET !== $request->getMethod()) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'scheduledTasks' => $this->probe->getScheduledTasks(),
            'executedTasks' => $this->probe->getExecutedTasks(),
            'failedTasks' => $this->probe->getFailedTasks(),
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 50]],
        ];
    }
}
