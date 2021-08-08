<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLockBagMiddleware implements PostExecutionMiddlewareInterface, OrderedMiddlewareInterface
{
    private const TASK_LOCK_MASK = '_symfony_scheduler_foo_';

    private LoggerInterface $logger;
    private LockFactory $lockFactory;

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
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
        $accessLockBag = $task->getAccessLockBag();
        if (!$accessLockBag instanceof AccessLockBag) {
            throw new RuntimeException(sprintf('The task "%s" must be linked to an access lock bag, consider using %s::execute() or %s::schedule()', $task->getName(), WorkerInterface::class, SchedulerInterface::class));
        }

        $lock = $this->lockFactory->createLockFromKey($accessLockBag->getKey());
        $lock->release();

        $this->logger->info(sprintf('The lock for task "%s" has been released', $task->getName()));

        $task->setAccessLockBag();
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 5;
    }

    public static function createKey(TaskInterface $task): Key
    {
        return new Key(sprintf('%s_%s', self::TASK_LOCK_MASK, $task->getName()));
    }
}
