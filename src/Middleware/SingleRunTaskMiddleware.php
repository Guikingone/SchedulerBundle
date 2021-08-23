<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use function in_array;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SingleRunTaskMiddleware implements PostExecutionMiddlewareInterface, OrderedMiddlewareInterface, RequiredMiddlewareInterface
{
    private LoggerInterface $logger;
    private SchedulerInterface $scheduler;

    public function __construct(
        SchedulerInterface $scheduler,
        ?LoggerInterface $logger = null
    ) {
        $this->scheduler = $scheduler;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
        if (in_array($task->getExecutionState(), [TaskInterface::INCOMPLETE, TaskInterface::TO_RETRY], true)) {
            $this->logger->warning(sprintf('The task "%s" is marked as incomplete or to retry, the "is_single" option is not used', $task->getName()));

            return;
        }

        if (!$task->isSingleRun()) {
            return;
        }

        $this->scheduler->pause($task->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 15;
    }
}
