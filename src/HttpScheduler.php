<?php

declare(strict_types=1);

namespace SchedulerBundle;

use BadMethodCallException;
use Closure;
use DateTimeZone;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Pool\Configuration\SchedulerConfiguration;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpScheduler implements SchedulerInterface
{
    public function __construct(
        private string $externalSchedulerEndpoint,
        private SerializerInterface $serializer,
        private HttpClientInterface $httpClient
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        $response = $this->httpClient->request(method: 'POST', url: sprintf('%s/tasks', $this->externalSchedulerEndpoint), options: [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->serializer->serialize(data: $task, format: 'json'),
        ]);

        if (201 !== $response->getStatusCode()) {
            throw new RuntimeException(message: sprintf('The task "%s" cannot be scheduled', $task->getName()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        $response = $this->httpClient->request(method: 'DELETE', url: sprintf('%s/task/%s', $this->externalSchedulerEndpoint, $taskName));

        if (204 !== $response->getStatusCode()) {
            throw new RuntimeException(message: sprintf('The task "%s" cannot be unscheduled', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        $response = $this->httpClient->request(method: 'POST', url: sprintf('%s/tasks:yield', $this->externalSchedulerEndpoint), options: [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'task' => $name,
                'async' => $async,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(message: sprintf('The task "%s" cannot be yielded', $name));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preempt(string $taskToPreempt, Closure $filter): void
    {
        throw new BadMethodCallException(message: sprintf('The %s::class cannot preempt tasks', self::class));
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task, bool $async = false): void
    {
        $response = $this->httpClient->request(method: 'PUT', url: sprintf('%s/task/%s', $this->externalSchedulerEndpoint, $taskName), options: [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'task' => $this->serializer->serialize(data: $task, format: 'json'),
                'async' => $async,
            ],
        ]);

        if (204 !== $response->getStatusCode()) {
            throw new RuntimeException(message: sprintf('The task "%s" cannot be updated', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName, bool $async = false): void
    {
        $response = $this->httpClient->request(method: 'POST', url: sprintf('%s/task/%s:pause', $this->externalSchedulerEndpoint, $taskName), options: [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'async' => $async,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(message: sprintf('The task "%s" cannot be paused', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $response = $this->httpClient->request(method: 'POST', url: sprintf('%s/task/%s:resume', $this->externalSchedulerEndpoint, $taskName), options: [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(message: sprintf('The task "%s" cannot be resumed', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(bool $lazy = false): TaskListInterface|LazyTaskList
    {
        $response = $this->httpClient->request(method: 'GET', url: sprintf('%s/tasks', $this->externalSchedulerEndpoint), options: [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'lazy' => $lazy,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(message: 'The tasks cannot be retrieved');
        }

        $list = $this->serializer->deserialize(data: $response->getContent(), type: TaskInterface::class.'[]', format: 'json');

        return new TaskList(tasks: $list);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(bool $lazy = false, bool $strict = false): TaskListInterface|LazyTaskList
    {
        $response = $this->httpClient->request(method: 'GET', url: sprintf('%s/tasks:due', $this->externalSchedulerEndpoint), options: [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'lazy' => $lazy,
                'strict' => $strict,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(message: 'The due tasks cannot be retrieved');
        }

        $list = $this->serializer->deserialize(data: $response->getContent(), type: TaskInterface::class.'[]', format: 'json');

        return new TaskList(tasks: $list);
    }

    /**
     * {@inheritdoc}
     */
    public function next(bool $lazy = false): TaskInterface|LazyTask
    {
        $response = $this->httpClient->request(method: 'GET', url: sprintf('%s/tasks:next', $this->externalSchedulerEndpoint), options: [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'lazy' => $lazy,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(message: 'The next task cannot be retrieved');
        }

        return $this->serializer->deserialize(data: $response->getContent(), type: TaskInterface::class, format: 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $response = $this->httpClient->request(method: 'POST', url: sprintf('%s/scheduler:reboot', $this->externalSchedulerEndpoint));

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(message: 'The scheduler cannot be rebooted');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): DateTimeZone
    {
        $response = $this->httpClient->request(method: 'GET', url: sprintf('%s/scheduler:timezone', $this->externalSchedulerEndpoint), options: [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(message: 'The scheduler timezone cannot be retrieved');
        }

        $configuration = $this->serializer->deserialize(data: $response->getContent(), type: SchedulerConfiguration::class, format: 'json');

        return $configuration->getTimezone();
    }

    /**
     * {@inheritdoc}
     */
    public function getPoolConfiguration(): SchedulerConfiguration
    {
        $response = $this->httpClient->request(method: 'GET', url: sprintf('%s/scheduler:configuration', $this->externalSchedulerEndpoint), options: [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(message: 'The scheduler configuration cannot be retrieved');
        }

        return $this->serializer->deserialize(data: $response->getContent(), type: SchedulerConfiguration::class, format: 'json');
    }
}
