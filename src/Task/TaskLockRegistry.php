<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Exception\InvalidArgumentException;
use Symfony\Component\Lock\LockInterface;
use function array_key_exists;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockRegistry implements TaskLockRegistryInterface
{
    /**
     * @var LockInterface[]
     */
    private array $locks;
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->locks = [];
        $this->logger = $logger ?? new NullLogger();
    }

    public function add(TaskInterface $task, LockInterface $lock): void
    {
        if (array_key_exists($task->getName(), $this->locks)) {
            $this->logger->warning(sprintf('A lock already exist for the task "%s"', $task->getName()));

            return;
        }

        $this->logger->info(sprintf('The lock related to the task "%s" has been added', $task->getName()));

        $this->locks[$task->getName()] = $lock;
    }

    public function find(TaskInterface $task): LockInterface
    {
        if (!array_key_exists($task->getName(), $this->locks)) {
            throw new InvalidArgumentException(sprintf('The task "%s" is not currently handled by a lock', $task->getName()));
        }

        $this->logger->info(sprintf('The lock related to the task "%s" has been found', $task->getName()));

        return $this->locks[$task->getName()];
    }

    public function remove(TaskInterface $task): void
    {
        unset($this->locks[$task->getName()]);

        $this->logger->info(sprintf('The lock related to the task "%s" has been removed', $task->getName()));
    }
}
