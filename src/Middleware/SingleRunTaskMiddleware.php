<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use function in_array;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\TransportInterface;

use SchedulerBundle\Worker\WorkerInterface;

use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SingleRunTaskMiddleware implements PostExecutionMiddlewareInterface, OrderedMiddlewareInterface, RequiredMiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private TransportInterface $transport,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
        if (in_array(needle: $task->getExecutionState(), haystack: [TaskInterface::INCOMPLETE, TaskInterface::TO_RETRY], strict: true)) {
            $this->logger->warning(message: sprintf('The task "%s" is marked as incomplete or to retry, the "is_single" option is not used', $task->getName()));

            return;
        }

        if (!$task->isSingleRun()) {
            return;
        }

        if ($task->isDeleteAfterExecute()) {
            $this->transport->delete(name: $task->getName());

            return;
        }

        $this->transport->pause(name: $task->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 15;
    }
}
