<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use SchedulerBundle\Probe\Probe;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use function rawurldecode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeStateSubscriber implements EventSubscriberInterface
{
    private Probe $probe;
    private string $path;

    public function __construct(Probe $probe, string $path = '/_probe')
    {
        $this->probe = $probe;
        $this->path = $path;
    }

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
            'scheduledTasks' => $this->probe->getScheduledTasks()->count(),
            'executedTasks' => $this->probe->getExecutedTasks()->count(),
            'failedTasks' => $this->probe->getFailedTasks()->count(),
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
