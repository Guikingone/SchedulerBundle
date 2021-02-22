<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware\Assets;

use SchedulerBundle\Middleware\OrderedMiddlewareInterface;
use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
class OrderedMiddleware implements PreSchedulingMiddlewareInterface, OrderedMiddlewareInterface
{
    public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
    }

    public function getPriority(): int
    {
        return 1;
    }
}
