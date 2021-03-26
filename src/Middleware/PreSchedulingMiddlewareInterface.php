<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface PreSchedulingMiddlewareInterface
{
    /**
     * Allow to execute logic before scheduling the task
     * (the @param TaskInterface $task is the one passed through {@see SchedulerInterface::schedule()} and BEFORE any modification).
     *
     *
     * @throws Throwable If an error|exception occurs, it must be thrown back.
     */
    public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler): void;
}
