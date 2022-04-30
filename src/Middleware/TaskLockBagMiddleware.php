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
final class TaskLockBagMiddleware implements PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface, OrderedMiddlewareInterface
{
    private const TASK_LOCK_MASK = '_symfony_scheduler_foo_';

    private LoggerInterface $logger;

    public function __construct(
        private LockFactory $lockFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function preExecute(TaskInterface $task): void
    {
        $key = self::createKey(task: $task);

        dump($task);

        $lock = $this->lockFactory->createLockFromKey(key: $key, ttl: null, autoRelease: false);
        if (!$lock->acquire()) {
            $this->logger->warning(message: sprintf('The lock related to the task "%s" cannot be acquired', $task->getName()));
        }

        $task->setAccessLockBag(bag: new AccessLockBag(key: $key));
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task, WorkerInterface $worker): void
    {
        $accessLockBag = $task->getAccessLockBag();
        if (!$accessLockBag instanceof AccessLockBag) {
            throw new RuntimeException(message: sprintf('The task "%s" must be linked to an access lock bag, consider using %s::execute() or %s::schedule()', $task->getName(), WorkerInterface::class, SchedulerInterface::class));
        }

        if (!$accessLockBag->getKey() instanceof Key) {
            return;
        }

        $lock = $this->lockFactory->createLockFromKey(key: $accessLockBag->getKey());
        $lock->release();

        $this->logger->info(message: sprintf('The lock for task "%s" has been released', $task->getName()));

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
        return new Key(resource: sprintf('%s_%s', self::TASK_LOCK_MASK, $task->getName()));
    }
}
