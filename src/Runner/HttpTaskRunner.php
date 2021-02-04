<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

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
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        if (!$task instanceof HttpTask) {
            $task->setExecutionState(TaskInterface::ERRORED);

            return new Output($task, null, Output::ERROR);
        }

        $task->setExecutionState(TaskInterface::RUNNING);

        try {
            $response = $this->httpClient->request($task->getMethod(), $task->getUrl(), $task->getClientOptions());
            $task->setExecutionState(TaskInterface::SUCCEED);

            return new Output($task, $response->getContent());
        } catch (Throwable $throwable) {
            $task->setExecutionState(TaskInterface::ERRORED);

            return new Output($task, $throwable->getMessage(), Output::ERROR);
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
