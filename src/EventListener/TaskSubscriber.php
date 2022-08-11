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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

use function array_key_exists;
use function rawurldecode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    /**
     * @param string $tasksPath The path that trigger this listener
     */
    public function __construct(
        private SchedulerInterface $scheduler,
        private WorkerInterface $worker,
        private EventDispatcherInterface $eventDispatcher,
        private SerializerInterface $serializer,
        LoggerInterface $logger = null,
        private string $tasksPath = '/_tasks'
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        $request = $requestEvent->getRequest();
        if ($this->tasksPath !== rawurldecode(string: $request->getPathInfo())) {
            return;
        }

        $query = $request->query->all();
        if (Request::METHOD_GET === $request->getMethod() && (!array_key_exists(key: 'name', array: $query) && !array_key_exists(key: 'expression', array: $query))) {
            throw new InvalidArgumentException(message: 'A GET request should at least contain a task name or its expression!');
        }

        $tasks = $this->scheduler->getTasks();

        if (array_key_exists(key: 'name', array: $query)) {
            $request->attributes->set(key: 'task_filter', value: $query['name']);
            $tasks = $tasks->filter(filter: static fn (TaskInterface $task): bool => $query['name'] === $task->getName());
        }

        if (array_key_exists(key: 'expression', array: $query)) {
            $request->attributes->set(key: 'task_filter', value: $query['expression']);
            $tasks = $tasks->filter(filter: static fn (TaskInterface $task): bool => $query['expression'] === $task->getExpression());
        }

        $this->eventDispatcher->addSubscriber(subscriber: new StopWorkerOnTaskLimitSubscriber(maximumTasks: $tasks->count(), logger: $this->logger));

        $tasks = $tasks->toArray(keepKeys: false);

        try {
            $this->worker->execute(WorkerConfiguration::create(), ...$tasks);
        } catch (Throwable $throwable) {
            $requestEvent->setResponse(response: new JsonResponse(data: [
                'code' => JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ], status: JsonResponse::HTTP_INTERNAL_SERVER_ERROR));

            return;
        }

        $requestEvent->setResponse(response: new Response(content: $this->serializer->serialize(data: $tasks, format: 'json'), status: Response::HTTP_OK, headers: [
            'Content-Type' => 'application/json',
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
