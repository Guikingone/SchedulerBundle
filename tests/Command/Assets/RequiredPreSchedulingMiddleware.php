<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command\Assets;

use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\RequiredMiddlewareInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RequiredPreSchedulingMiddleware implements PreSchedulingMiddlewareInterface, RequiredMiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
    }
}
