<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command\Assets;

use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class PostSchedulingMiddleware implements PostSchedulingMiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function postScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
    }
}
