<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Serializer\Serializer;
use Throwable;
use function array_key_exists;
use function rawurldecode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskSubscriber implements EventSubscriberInterface
{
    private SchedulerInterface $scheduler;
    private string $tasksPath;
    private WorkerInterface $worker;
    private EventDispatcherInterface $eventDispatcher;
    private Serializer $serializer;
    private LoggerInterface $logger;

    /**
     * @param string $tasksPath The path that trigger this listener
     */
    public function __construct(
        SchedulerInterface $scheduler,
        WorkerInterface $worker,
        EventDispatcherInterface $eventDispatcher,
        Serializer $serializer,
        LoggerInterface $logger = null,
        string $tasksPath = '/_tasks'
    ) {
        $this->scheduler = $scheduler;
        $this->worker = $worker;
        $this->eventDispatcher = $eventDispatcher;
        $this->serializer = $serializer;
        $this->logger = $logger ?? new NullLogger();
        $this->tasksPath = $tasksPath;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($this->tasksPath !== rawurldecode($request->getPathInfo())) {
            return;
        }

        $query = $request->query->all();
        if (Request::METHOD_GET === $request->getMethod() && (!array_key_exists('name', $query) && !array_key_exists('expression', $query))) {
            throw new InvalidArgumentException('A GET request should at least contain a task name or its expression!');
        }

        $tasks = $this->scheduler->getTasks();

        if (array_key_exists('name', $query) && $name = $query['name']) {
            $request->attributes->set('task_filter', $name);
            $tasks = $tasks->filter(fn (TaskInterface $task): bool => $name === $task->getName());
        }

        if (array_key_exists('expression', $query) && $expression = $query['expression']) {
            $request->attributes->set('task_filter', $expression);
            $tasks = $tasks->filter(fn (TaskInterface $task): bool => $expression === $task->getExpression());
        }

        $this->eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber($tasks->count(), $this->logger));

        $tasks = $tasks->toArray(false);

        try {
            $this->worker->execute([], ...$tasks);
        } catch (Throwable $throwable) {
            $event->setResponse(new JsonResponse([
                'code' => JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR));

            return;
        }

        $event->setResponse(new JsonResponse([
            'code' => JsonResponse::HTTP_OK,
            'tasks' => $this->serializer->normalize($tasks, 'json'),
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
