<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\TaskBag\LockTaskBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockBagMiddleware implements PreSchedulingMiddlewareInterface, PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface, PreListingMiddlewareInterface
{
    private const TASK_LOCK_MASK = '_symfony_scheduler_';

    private LockFactory $lockFactory;
    private LoggerInterface $logger;

    public function __construct(
        LockFactory $lockFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->lockFactory = $lockFactory;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler): void
    {
        $executionLockTaskBag = $task->getExecutionLockBag();
        if ($executionLockTaskBag instanceof LockTaskBag) {
            $this->logger->info(sprintf('The task "%s" has already an execution lock bag', $task->getName()));

            return;
        }

        $key = $this->createKey($task);

        $lock = $this->lockFactory->createLockFromKey($key, null, false);
        if (!$lock->acquire(false)) {
            $this->logger->info(sprintf('The lock related to the task "%s" cannot be acquired, it will be created before executing the task', $task->getName()));

            return;
        }

        $task->setExecutionLockBag(new LockTaskBag($key));
    }

    /**
     * {@inheritdoc}
     */
    public function preExecute(TaskInterface $task): void
    {
        $executionLockTaskBag = $task->getExecutionLockBag();
        if (!$executionLockTaskBag instanceof LockTaskBag) {
            $task->setExecutionLockBag(new LockTaskBag($this->createKey($task)));

            $this->logger->info(sprintf('An execution lock bag has been created for task "%s"', $task->getName()));
        }

        $executionLockTaskBag = $task->getExecutionLockBag();

        if (!$executionLockTaskBag->getKey() instanceof Key) {
            $task->setExecutionLockBag(new LockTaskBag($this->createKey($task)));
        }

        $executionLockTaskBag = $task->getExecutionLockBag();

        $lock = $this->lockFactory->createLockFromKey($executionLockTaskBag->getKey());
        $lock->acquire(true);
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task): void
    {
        $this->releaseExecutionLock($task);
        $this->releaseAccessLock($task);
    }

    /**
     * {@inheritdoc}
     */
    public function preListing(TaskInterface $task, TaskListInterface $taskList): void
    {
        $key = new Key(sprintf('_scheduler_access_%s', $task->getName()));
        $taskLock = $this->lockFactory->createLockFromKey($key, null, false);

        if ($taskLock->isAcquired()) {
            $taskList->remove($task->getName());
        }

        $taskLock->acquire();

        $task->setAccessLockBag(new AccessLockBag($key));
    }

    private function createKey(TaskInterface $task): Key
    {
        return new Key(sprintf('%s_%s_%s', self::TASK_LOCK_MASK, $task->getName(), $task->isSingleRun() ? 'single' : ''));
    }

    private function releaseExecutionLock(TaskInterface $task): void
    {
        $executionLockTaskBag = $task->getExecutionLockBag();
        if (!$executionLockTaskBag instanceof AccessLockBag) {
            return;
        }

        $key = $executionLockTaskBag->getKey();
        if (!$key instanceof Key) {
            return;
        }

        $lock = $this->lockFactory->createLockFromKey($key);
        $lock->release();

        $this->logger->info(sprintf('The lock for task "%s" has been released', $task->getName()));
    }

    private function releaseAccessLock(TaskInterface $task): void
    {
        $accessLockBag = $task->getAccessLockBag();
        if (!$accessLockBag instanceof AccessLockBag) {
            return;
        }

        $key = $accessLockBag->getKey();
        if (!$key instanceof Key) {
            return;
        }

        $lock = $this->lockFactory->createLockFromKey($key);
        $lock->release();

        $this->logger->info(sprintf('The access lock for task "%s" has been released', $task->getName()));
    }
}
