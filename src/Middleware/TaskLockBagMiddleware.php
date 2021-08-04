<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\TaskBag\ExecutionLockBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockBagMiddleware implements PreSchedulingMiddlewareInterface, PostWorkerStartMiddlewareInterface, PostExecutionMiddlewareInterface
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
        $accessLockBag = $task->getAccessLockBag();
        if ($accessLockBag instanceof AccessLockBag) {
            $this->logger->info(sprintf('The task "%s" has already an access lock bag', $task->getName()));

            return;
        }

        $task->setAccessLockBag(new AccessLockBag($this->createKey($task)));
    }

    /**
     * {@inheritdoc}
     */
    public function postWorkerStart(TaskListInterface $taskList): void
    {
        $taskList->walk(function (TaskInterface $task) use (&$taskList): void {
            $accessLockBag = $task->getAccessLockBag();
            if (!$accessLockBag instanceof AccessLockBag) {
                $this->logger->info(sprintf('The task "%s" does not have an access lock bag, consider calling %s::schedule()', $task->getName(), SchedulerInterface::class));

                return;
            }

            $lock = $this->lockFactory->createLockFromKey($accessLockBag->getKey(), null, false);
            dump($lock->acquire());

            if (!$lock->acquire()) {
                dump($task->getName());
                $this->logger->info(sprintf('The lock related to the task "%s" cannot be acquired, it will be created before executing the task', $task->getName()));

                $taskList->remove($task->getName());

                return;
            }

            $task->setExecutionLockBag(new ExecutionLockBag($lock));
        });
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task): void
    {
        $executionLockBag = $task->getExecutionLockBag();
        if (!$executionLockBag instanceof ExecutionLockBag) {
            return;
        }

        $lock = $executionLockBag->getLock();
        $lock->release();

        $this->logger->info(sprintf('The lock for task "%s" has been released', $task->getName()));

        $task->setExecutionLockBag();
    }

    private function createKey(TaskInterface $task): Key
    {
        return new Key(sprintf('%s_%s', self::TASK_LOCK_MASK, $task->getName()));
    }
}
