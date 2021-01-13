<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskSubscriber implements EventSubscriberInterface
{
    private $scheduler;
    private $tasksPath;
    private $worker;
    private $eventDispatcher;
    private $serializer;
    private $logger;

    /**
     * @param string $tasksPath The path that trigger this listener
     */
    public function __construct(SchedulerInterface $scheduler, WorkerInterface $worker, EventDispatcherInterface $eventDispatcher, SerializerInterface $serializer, LoggerInterface $logger = null, string $tasksPath = '/_tasks')
    {
        $this->scheduler = $scheduler;
        $this->worker = $worker;
        $this->eventDispatcher = $eventDispatcher;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->tasksPath = $tasksPath;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($this->tasksPath !== rawurldecode($request->getPathInfo())) {
            return;
        }

        $query = $request->query->all();
        if (Request::METHOD_GET === $request->getMethod() && (!\array_key_exists('name', $query) && !\array_key_exists('expression', $query))) {
            throw new \InvalidArgumentException('A GET request should at least contains a task name or its expression!');
        }

        $tasks = $this->scheduler->getTasks();

        if (\array_key_exists('name', $query) && $name = $query['name']) {
            $request->attributes->set('task_filter', $name);
            $tasks->filter(function (TaskInterface $task) use ($name): bool {
                return $name === $task->getName();
            });
        }

        if (\array_key_exists('expression', $query) && $expression = $query['expression']) {
            $request->attributes->set('task_filter', $expression);
            $tasks->filter(function (TaskInterface $task) use ($expression): bool {
                return $expression === $task->getExpression();
            });
        }

        $this->eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber($tasks->count(), $this->logger));

        try {
            $this->worker->execute([], ...$tasks);
        } catch (\Throwable $throwable) {
            $event->setResponse(new JsonResponse([
                'code' => JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR));

            return;
        }

        $event->setResponse(new JsonResponse([
            'code' => JsonResponse::HTTP_OK,
            'tasks' => $this->serializer->serialize($tasks->toArray(), 'json'),
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
