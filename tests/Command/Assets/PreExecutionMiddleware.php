<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command\Assets;

use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class PreExecutionMiddleware implements PreExecutionMiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function preExecute(TaskInterface $task): void
    {
    }
}
