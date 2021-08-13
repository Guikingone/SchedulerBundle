<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTaskMiddleware implements PreExecutionMiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function preExecute(TaskInterface $task): void
    {
        if (!$task instanceof ProbeTask) {
            return;
        }

        if (0 === $task->getDelay()) {
            return;
        }

        usleep($task->getDelay());
    }
}
