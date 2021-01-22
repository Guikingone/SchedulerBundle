<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskMessage
{
    /**
     * @var TaskInterface
     */
    private $task;

    /**
     * @var int
     */
    private $workerTimeout;

    public function __construct(TaskInterface $task, int $workerTimeout = 1)
    {
        $this->task = $task;
        $this->workerTimeout = $workerTimeout;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getWorkerTimeout(): int
    {
        return $this->workerTimeout;
    }
}
