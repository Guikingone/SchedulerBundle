<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\HttpClient\HttpClient;
use SchedulerBundle\Task\HttpTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpTaskRunner implements RunnerInterface
{
    public function __construct(private ?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task, WorkerInterface $worker): Output
    {
        if (!$task instanceof HttpTask) {
            return new Output(task: $task, output: null, type: Output::ERROR);
        }

        try {
            $response = $this->httpClient->request(method: $task->getMethod(), url: $task->getUrl(), options: $task->getClientOptions());
            return new Output(task: $task, output: $response->getContent());
        } catch (Throwable $throwable) {
            return new Output(task: $task, output: $throwable->getMessage(), type: Output::ERROR);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof HttpTask;
    }
}
