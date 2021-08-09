<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PostSchedulingMiddlewareInterface
{
    /**
     * Allow executing logic after scheduling the task (the @param TaskInterface $task is the one returned via {@see SchedulerInterface::schedule()} and AFTER the transport stores it).
     *
     * @throws Throwable If an error|exception occurs, it must be thrown back.
     */
    public function postScheduling(TaskInterface $task, SchedulerInterface $scheduler): void;
}
