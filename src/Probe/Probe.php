<?php

declare(strict_types=1);

namespace SchedulerBundle\Probe;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
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
        private SchedulerInterface $scheduler,
        private WorkerInterface $worker,
        private ?ClockInterface $clock = null,
    ) {
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function getExecutedTasks(): int
    {
        $tasks = $this->scheduler->getTasks();

        $filteredTasks = $tasks->filter(filter: function (TaskInterface $task): bool {
            $lastExecutionDate = $task->getLastExecution();
            if (!$lastExecutionDate instanceof DateTimeImmutable) {
                return false;
            }

            $currentDate = $this->clock instanceof ClockInterface
                ? $this->clock->now()->format(format: 'Y-m-d h:i')
                : (new DateTimeImmutable())->format(format: 'Y-m-d h:i')
            ;

            return $lastExecutionDate->format(format: 'Y-m-d h:i') === $currentDate;
        });

        return $filteredTasks->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedTasks(): int
    {
        $failedTasks = $this->worker->getFailedTasks();

        return $failedTasks->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getScheduledTasks(): int
    {
        $tasks = $this->scheduler->getTasks();

        return $tasks->filter(filter: static fn (TaskInterface $task): bool => null !== $task->getScheduledAt())->count();
    }
}
