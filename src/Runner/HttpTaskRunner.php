<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Runner;

use Symfony\Component\HttpClient\HttpClient;
use SchedulerBundle\Task\HttpTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class HttpTaskRunner implements RunnerInterface
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        $task->setExecutionState(TaskInterface::RUNNING);

        try {
            $response = $this->httpClient->request($task->getMethod(), $task->getUrl(), $task->getClientOptions());
            $task->setExecutionState(TaskInterface::SUCCEED);

            return new Output($task, $response->getContent());
        } catch (\Throwable $exception) {
            $task->setExecutionState(TaskInterface::ERRORED);

            return new Output($task, $exception->getMessage(), Output::ERROR);
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
