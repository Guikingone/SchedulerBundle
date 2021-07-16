<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\TaskBag\LockTaskBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockBagMiddleware implements PreSchedulingMiddlewareInterface
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
        if ($task->getExecutionLockBag() instanceof LockTaskBag) {
            return;
        }

        $key = new Key(sprintf('%s_%s_%s', self::TASK_LOCK_MASK, $task->getName(), (new DateTimeImmutable())->format($task->isSingleRun() ? 'Y_m_d_h' : 'Y_m_d_h_i')));

        $task->setExecutionLockBag(new LockTaskBag($key));
//
//        try {
//            $scheduler->update($task->getName(), $task->setExecutionLockBag(new LockTaskBag($key)));
//        } catch (Throwable $throwable) {
//            $this->logger->critical(sprintf('The lock for the task "%s" cannot be serialized / stored, consider using a supporting store', $task->getName()));
//
//            $lock->release();
//        }
    }
}
