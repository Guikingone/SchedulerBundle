<?php

declare(strict_types=1);

namespace SchedulerBundle;

use Closure;
use DateTimeZone;
use SchedulerBundle\Pool\Configuration\SchedulerConfiguration;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpScheduler implements SchedulerInterface
{
    private string $externalSchedulerEndpoint;
    private HttpClientInterface $httpClient;
    private SerializerInterface $serializer;

    public function __construct(
        string $externalSchedulerEndpoint,
        SerializerInterface $serializer,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->serializer = $serializer;
        $this->externalSchedulerEndpoint = $externalSchedulerEndpoint;

        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        // TODO: Implement schedule() method.
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        // TODO: Implement unschedule() method.
    }

    /**
     * {@inheritdoc}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        // TODO: Implement yieldTask() method.
    }

    /**
     * {@inheritdoc}
     */
    public function preempt(string $taskToPreempt, Closure $filter): void
    {
        // TODO: Implement preempt() method.
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task, bool $async = false): void
    {
        // TODO: Implement update() method.
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName, bool $async = false): void
    {
        // TODO: Implement pause() method.
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        // TODO: Implement resume() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(bool $lazy = false): TaskListInterface
    {
        // TODO: Implement getTasks() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(bool $lazy = false, bool $strict = false): TaskListInterface
    {
        // TODO: Implement getDueTasks() method.
    }

    /**
     * {@inheritdoc}
     */
    public function next(bool $lazy = false): TaskInterface
    {
        // TODO: Implement next() method.
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        // TODO: Implement reboot() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): DateTimeZone
    {
        $response = $this->httpClient->request('GET', sprintf('%s/configuration', $this->externalSchedulerEndpoint));

        $configuration = $this->serializer->deserialize($response->toArray(), SchedulerConfiguration::class, 'json');

        return $configuration->getTimeZone();
    }

    /**
     * {@inheritdoc}
     */
    public function getPoolConfiguration(): SchedulerConfiguration
    {
        $response = $this->httpClient->request('GET', sprintf('%s/configuration', $this->externalSchedulerEndpoint));

        return $this->serializer->deserialize($response->toArray(), SchedulerConfiguration::class, 'json');
    }
}
