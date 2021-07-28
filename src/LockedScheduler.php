<?php

declare(strict_types=1);

namespace SchedulerBundle;

use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\TaskBag\AccessLockBag;
use Symfony\Component\Lock\LockFactory;
use Throwable;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LockedScheduler implements SchedulerInterface
{
    private LockFactory $lockFactory;
    private SchedulerInterface $scheduler;
    private LoggerInterface $logger;

    public function __construct(
        SchedulerInterface $scheduler,
        LockFactory $lockFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->scheduler = $scheduler;
        $this->lockFactory = $lockFactory;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        $lock = $this->lockFactory->createLock(sprintf('_scheduler_schedule_%s', $task->getName()));
        $lock->acquire(true);

        try {
            $this->scheduler->schedule($task);
        } catch (Throwable $throwable) {
            $this->logger->warning(sprintf('The task "%s" cannot be scheduled using %s::%s(), the related lock will be released', $task->getName(), self::class, __METHOD__));

            throw $throwable;
        } finally {
            $lock->release();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        // TODO: Implement unschedule() method.
    }

    /**
     * {@inheritdoc}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        // TODO: Implement yieldTask() method.
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task): void
    {
        // TODO: Implement update() method.
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName, bool $async = false): void
    {
        // TODO: Implement pause() method.
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        // TODO: Implement resume() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(bool $lazy = false): TaskListInterface
    {
        // TODO: Implement getTasks() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(bool $lazy = false): TaskListInterface
    {
        $lock = $this->lockFactory->createLock('_scheduler_due_tasks');
        $lock->acquire(true);

        try {
            $dueTasks = $this->scheduler->getDueTasks($lazy);
            $dueTasks->walk(function (TaskInterface $task): void {
                $taskLock = $this->lockFactory->createLock(sprintf('_scheduler_due_%s', $task->getName()), null, false);
                $taskLock->acquire(true);

                $task->setAccessLockBag(new AccessLockBag($taskLock));
            });

            return $dueTasks;
        } catch (Throwable $throwable) {
            $this->logger->warning('The due tasks cannot be returned, the related locks will be released');

            throw $throwable;
        } finally {
            $lock->release();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function next(bool $lazy = false): TaskInterface
    {
        // TODO: Implement next() method.
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        // TODO: Implement reboot() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): DateTimeZone
    {
    }
}
