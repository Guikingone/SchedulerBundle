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
use Symfony\Component\HttpClient\HttpClient;
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
        private ?HttpClientInterface $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        $response = $this->httpClient->request('POST', sprintf('%s/tasks', $this->externalSchedulerEndpoint), [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->serializer->serialize($task, 'json'),
        ]);

        if (201 !== $response->getStatusCode()) {
            throw new RuntimeException(sprintf('The task "%s" cannot be scheduled', $task->getName()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        $response = $this->httpClient->request('DELETE', sprintf('%s/task/%s', $this->externalSchedulerEndpoint, $taskName));

        if (204 !== $response->getStatusCode()) {
            throw new RuntimeException(sprintf('The task "%s" cannot be unscheduled', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        $response = $this->httpClient->request('POST', sprintf('%s/tasks:yield', $this->externalSchedulerEndpoint), [
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
            throw new RuntimeException(sprintf('The task "%s" cannot be yielded', $name));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preempt(string $taskToPreempt, Closure $filter): void
    {
        throw new BadMethodCallException(sprintf('The %s::class cannot preempt tasks', self::class));
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task, bool $async = false): void
    {
        $response = $this->httpClient->request('PUT', sprintf('%s/task/%s', $this->externalSchedulerEndpoint, $taskName), [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'task' => $this->serializer->serialize($task, 'json'),
                'async' => $async,
            ],
        ]);

        if (204 !== $response->getStatusCode()) {
            throw new RuntimeException(sprintf('The task "%s" cannot be updated', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName, bool $async = false): void
    {
        $response = $this->httpClient->request('POST', sprintf('%s/task/%s:pause', $this->externalSchedulerEndpoint, $taskName), [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'async' => $async,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(sprintf('The task "%s" cannot be paused', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $response = $this->httpClient->request('POST', sprintf('%s/task/%s:resume', $this->externalSchedulerEndpoint, $taskName), [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(sprintf('The task "%s" cannot be resumed', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(bool $lazy = false): TaskListInterface|LazyTaskList
    {
        $response = $this->httpClient->request('GET', sprintf('%s/tasks', $this->externalSchedulerEndpoint), [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'lazy' => $lazy,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('The tasks cannot be retrieved');
        }

        $list = $this->serializer->deserialize($response->getContent(), TaskInterface::class.'[]', 'json');

        return new TaskList($list);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(bool $lazy = false, bool $strict = false): TaskListInterface|LazyTaskList
    {
        $response = $this->httpClient->request('GET', sprintf('%s/tasks:due', $this->externalSchedulerEndpoint), [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'lazy' => $lazy,
                'strict' => $strict,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('The due tasks cannot be retrieved');
        }

        $list = $this->serializer->deserialize($response->getContent(), TaskInterface::class.'[]', 'json');

        return new TaskList($list);
    }

    /**
     * {@inheritdoc}
     */
    public function next(bool $lazy = false): TaskInterface|LazyTask
    {
        $response = $this->httpClient->request('GET', sprintf('%s/tasks:next', $this->externalSchedulerEndpoint), [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'lazy' => $lazy,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('The next task cannot be retrieved');
        }

        return $this->serializer->deserialize($response->getContent(), TaskInterface::class, 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $response = $this->httpClient->request('POST', sprintf('%s/scheduler:reboot', $this->externalSchedulerEndpoint));

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('The scheduler cannot be rebooted');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): DateTimeZone
    {
        $response = $this->httpClient->request('GET', sprintf('%s/scheduler:timezone', $this->externalSchedulerEndpoint), [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('The scheduler timezone cannot be retrieved');
        }

        $configuration = $this->serializer->deserialize($response->getContent(), SchedulerConfiguration::class, 'json');

        return $configuration->getTimezone();
    }

    /**
     * {@inheritdoc}
     */
    public function getPoolConfiguration(): SchedulerConfiguration
    {
        $response = $this->httpClient->request('GET', sprintf('%s/scheduler:configuration', $this->externalSchedulerEndpoint), [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('The scheduler configuration cannot be retrieved');
        }

        return $this->serializer->deserialize($response->getContent(), SchedulerConfiguration::class, 'json');
    }
}
