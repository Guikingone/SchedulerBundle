<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Worker\WorkerConfiguration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
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

    /**
     * @throws Throwable          {@see SchedulerInterface::getTasks()}
     * @throws ExceptionInterface {@see SerializerInterface::serialize()}
     */
    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        $request = $requestEvent->getRequest();
        if ($this->tasksPath !== rawurldecode($request->getPathInfo())) {
            return;
        }

        $query = $request->query->all();
        if (Request::METHOD_GET === $request->getMethod() && (!array_key_exists('name', $query) && !array_key_exists('expression', $query))) {
            throw new InvalidArgumentException('A GET request should at least contain a task name or its expression!');
        }

        $tasks = $this->scheduler->getTasks();

        if (array_key_exists('name', $query)) {
            $request->attributes->set('task_filter', $query['name']);
            $tasks = $tasks->filter(static fn (TaskInterface $task): bool => $query['name'] === $task->getName());
        }

        if (array_key_exists('expression', $query)) {
            $request->attributes->set('task_filter', $query['expression']);
            $tasks = $tasks->filter(static fn (TaskInterface $task): bool => $query['expression'] === $task->getExpression());
        }

        $this->eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber($tasks->count(), $this->logger));

        $tasks = $tasks->toArray(false);

        try {
            $this->worker->execute(WorkerConfiguration::create(), ...$tasks);
        } catch (Throwable $throwable) {
            $requestEvent->setResponse(new JsonResponse([
                'code' => JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR));

            return;
        }

        $requestEvent->setResponse(new JsonResponse([
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
