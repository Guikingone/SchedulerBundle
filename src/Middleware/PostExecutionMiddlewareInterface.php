<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PostExecutionMiddlewareInterface
{
    public function postExecute(TaskInterface $task): void;
}
