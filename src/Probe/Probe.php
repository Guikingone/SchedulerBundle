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
    public function __construct(
        private readonly SchedulerInterface $scheduler,
        private readonly WorkerInterface $worker
    ) {
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function getExecutedTasks(): int
    {
        return $this->scheduler->getTasks()->filter(filter: static function (TaskInterface $task): bool {
            $lastExecutionDate = $task->getLastExecution();
            if (!$lastExecutionDate instanceof DateTimeImmutable) {
                return false;
            }

            return $lastExecutionDate->format(format: 'Y-m-d h:i') === (new DateTimeImmutable())->format(format: 'Y-m-d h:i');
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
        return $this->scheduler->getTasks()->filter(filter: static fn (TaskInterface $task): bool => null !== $task->getScheduledAt())->count();
    }
}
