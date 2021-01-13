<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Messenger;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskMessage
{
    private $task;
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
