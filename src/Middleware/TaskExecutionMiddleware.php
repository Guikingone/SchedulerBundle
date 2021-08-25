<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use function usleep;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutionMiddleware implements PreExecutionMiddlewareInterface, OrderedMiddlewareInterface, RequiredMiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function preExecute(TaskInterface $task): void
    {
        $executionDelay = $task->getExecutionDelay();

        if (null !== $executionDelay) {
            usleep($executionDelay);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 1;
    }
}
