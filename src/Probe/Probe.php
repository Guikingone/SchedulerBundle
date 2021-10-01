<?php

declare(strict_types=1);

namespace SchedulerBundle\Probe;

use DateTimeImmutable;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Probe implements ProbeInterface
{
    private SchedulerInterface $scheduler;
    private WorkerInterface $worker;

    public function __construct(
        SchedulerInterface $scheduler,
        WorkerInterface $worker
    ) {
        $this->scheduler = $scheduler;
        $this->worker = $worker;
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function getExecutedTasks(): int
    {
        return $this->scheduler->getTasks()->filter(static function (TaskInterface $task): bool {
            $lastExecutionDate = $task->getLastExecution();
            if (!$lastExecutionDate instanceof DateTimeImmutable) {
                return false;
            }

            return $lastExecutionDate->format('Y-m-d h:i') === (new DateTimeImmutable())->format('Y-m-d h:i');
        })->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedTasks(): int
    {
        return $this->worker->getFailedTasks()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getScheduledTasks(): int
    {
        return $this->scheduler->getTasks()->filter(static fn (TaskInterface $task): bool => null !== $task->getScheduledAt())->count();
    }
}
