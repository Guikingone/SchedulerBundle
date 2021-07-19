<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\TaskBag\LockTaskBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockBagMiddleware implements PreSchedulingMiddlewareInterface, PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface
{
    public const TASK_LOCK_MASK = '_symfony_scheduler_';

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

        $task->setExecutionLockBag(new LockTaskBag($this->createKey($task)));
    }

    /**
     * {@inheritdoc}
     */
    public function preExecute(TaskInterface $task): void
    {
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
        $executionLockTaskBag = $task->getExecutionLockBag();

        $lock = $this->lockFactory->createLockFromKey($executionLockTaskBag->getKey());
        $lock->release();
    }

    private function createKey(TaskInterface $task): Key
    {
        return new Key(sprintf('%s_%s_%s', self::TASK_LOCK_MASK, $task->getName(), (new DateTimeImmutable())->format($task->isSingleRun() ? 'Y_m_d_h' : 'Y_m_d_h_i')));
    }
}
