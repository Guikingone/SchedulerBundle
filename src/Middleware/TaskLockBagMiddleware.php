<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Task\TaskLockRegistryInterface;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockBagMiddleware implements PreSchedulingMiddlewareInterface, PostWorkerStartMiddlewareInterface, PostExecutionMiddlewareInterface, OrderedMiddlewareInterface
{
    private const TASK_LOCK_MASK = '_symfony_scheduler_';

    private LockFactory $lockFactory;
    private TaskLockRegistryInterface $taskLockRegistry;
    private LoggerInterface $logger;

    public function __construct(
        LockFactory $lockFactory,
        TaskLockRegistryInterface $taskLockRegistry,
        ?LoggerInterface $logger = null
    ) {
        $this->lockFactory = $lockFactory;
        $this->taskLockRegistry = $taskLockRegistry;
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
    public function postWorkerStart(TaskListInterface $taskList, WorkerInterface $worker): void
    {
        $taskList->walk(function (TaskInterface $task) use (&$taskList, $worker): void {
            if (!$task->getAccessLockBag() instanceof AccessLockBag) {
                $this->logger->info(sprintf('The task "%s" does not have an access lock bag, consider calling %s::schedule() next time', $task->getName(), SchedulerInterface::class));

                $task->setAccessLockBag(new AccessLockBag($this->createKey($task)));
            }

            $accessLockBag = $task->getAccessLockBag();

            $lock = $this->lockFactory->createLockFromKey($accessLockBag->getKey(), null, false);
            if (!$lock->acquire()) {
                $this->logger->info(sprintf('The lock related to the task "%s" cannot be acquired, it will be created before executing the task', $task->getName()));

                $taskList->remove($task->getName());

                return;
            }

            $this->taskLockRegistry->add($task, $lock);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
        $lock = $this->taskLockRegistry->find($task);
        $lock->release();

        $this->logger->info(sprintf('The lock for task "%s" has been released', $task->getName()));

        $this->taskLockRegistry->remove($task);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 5;
    }

    private function createKey(TaskInterface $task): Key
    {
        return new Key(sprintf('%s_%s', self::TASK_LOCK_MASK, $task->getName()));
    }
}
