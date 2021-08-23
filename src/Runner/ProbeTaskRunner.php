<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use function array_key_exists;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTaskRunner implements RunnerInterface
{
    private HttpClientInterface $httpClient;

    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task, WorkerInterface $worker): Output
    {
        if (!$task instanceof ProbeTask) {
            return new Output($task, null, Output::ERROR);
        }

        try {
            $response = $this->httpClient->request('GET', $task->getExternalProbePath());
            $body = $response->toArray(true);
            if (!array_key_exists('failedTasks', $body) || ($task->getErrorOnFailedTasks() && 0 !== $body['failedTasks'])) {
                throw new RuntimeException('The probe state is invalid');
            }

            return new Output($task, 'The probe succeed');
        } catch (Throwable $throwable) {
            return new Output($task, $throwable->getMessage(), Output::ERROR);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof ProbeTask;
    }
}
